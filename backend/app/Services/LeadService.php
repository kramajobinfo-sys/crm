<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadAssignmentRule;
use App\Models\LeadStatus;
use App\Models\TimelineActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LeadService
{
    public function __construct(
        private readonly LeadScoringService $scoring,
        private readonly CustomerService $customers,
        private readonly DealService $deals,
        private readonly WorkflowService $workflows,
        private readonly DuplicateDetectionService $duplicates,
    ) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Lead::query()
            ->with(['source:id,name,code', 'status:id,name,code,color,is_won,is_lost', 'owner:id,name'])
            ->search($filters['q'] ?? null)
            ->when(($filters['converted'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(($filters['converted'] ?? null) === 'converted', fn ($q) => $q->whereNotNull('converted_to_customer_id'))
            ->when(!empty($filters['status_id']), fn ($q) => $q->where('status_id', $filters['status_id']))
            ->when(!empty($filters['source_id']), fn ($q) => $q->where('source_id', $filters['source_id']))
            ->when(!empty($filters['rating']), fn ($q) => $q->where('rating', $filters['rating']))
            ->when(!empty($filters['priority']), fn ($q) => $q->where('priority', $filters['priority']))
            ->when(($filters['follow_up'] ?? null) === 'overdue',
                fn ($q) => $q->open()->whereNotNull('follow_up_at')->where('follow_up_at', '<', now()))
            ->when(($filters['follow_up'] ?? null) === 'today',
                fn ($q) => $q->open()->whereBetween('follow_up_at', [now()->startOfDay(), now()->endOfDay()]))
            ->when(!empty($filters['owner_id']), function ($q) use ($filters) {
                if ($filters['owner_id'] === 'me') return $q->where('owner_id', auth()->id());
                if ($filters['owner_id'] === 'unassigned') return $q->whereNull('owner_id');
                return $q->where('owner_id', $filters['owner_id']);
            })
            ->orderByDesc('score')->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): Lead
    {
        return Lead::with([
            'source', 'status', 'lostReason:id,name', 'campaign:id,name', 'account:id,name,customer_no',
            'owner:id,name', 'branch:id,name', 'customer:id,name,customer_no',
            'deals' => fn ($q) => $q->with('stage:id,name,is_won,is_lost')->orderByDesc('id'),
            'products:id,name,sku',
            'addresses',
            'attachments' => fn ($q) => $q->with('uploader:id,name')->latest(),
            'timeline' => fn ($q) => $q->with('user:id,name')->orderByDesc('occurred_at')->limit(50),
        ])->findOrFail($id);
    }

    public function create(array $data): Lead
    {
        return DB::transaction(function () use ($data) {
            $data['lead_no'] ??= $this->nextLeadNo();
            $data['status_id'] ??= LeadStatus::where('is_default', true)->value('id');
            $products = $data['products'] ?? null;
            unset($data['products']);
            if (array_key_exists('custom_fields', $data)) {
                $data['custom_fields'] = app(CustomFieldService::class)->sanitize('lead', (array) $data['custom_fields']);
            }

            $lead = Lead::create($data);
            if ($products !== null) $this->syncProducts($lead, $products);

            // Explicit owner wins; otherwise let the rules decide.
            if (!$lead->owner_id) $this->autoAssign($lead);

            $this->scoring->apply($lead);
            TimelineActivity::record($lead, 'system', 'Lead created');
            $this->workflows->fireEvent('leads', 'lead.created', $lead);

            return $this->find($lead->id);
        });
    }

    public function update(Lead $lead, array $data): Lead
    {
        return DB::transaction(function () use ($lead, $data) {
            $beforeStatus = $lead->status_id;
            $beforeOwner  = $lead->owner_id;

            $products = $data['products'] ?? null;
            unset($data['products']);
            if (array_key_exists('custom_fields', $data)) {
                // Merge onto existing values so a partial update doesn't wipe untouched fields.
                $data['custom_fields'] = array_merge($lead->custom_fields ?? [],
                    app(CustomFieldService::class)->sanitize('lead', (array) $data['custom_fields']));
            }
            $lead->update($data);
            if ($products !== null) $this->syncProducts($lead, $products);

            if (array_key_exists('status_id', $data) && $data['status_id'] !== $beforeStatus) {
                $from = LeadStatus::find($beforeStatus)?->name ?? '—';
                $to   = LeadStatus::find($data['status_id'])?->name ?? '—';
                TimelineActivity::record($lead, 'status_change', "Status changed from {$from} to {$to}",
                    null, ['from' => $beforeStatus, 'to' => $data['status_id']]);
                $this->workflows->fireEvent('leads', 'lead.status_changed', $lead);
            }
            if (array_key_exists('owner_id', $data) && $data['owner_id'] !== $beforeOwner) {
                TimelineActivity::record($lead, 'system', 'Owner reassigned');
                app(CrmNotifier::class)->leadAssigned($lead);
            }

            $this->scoring->apply($lead);
            return $this->find($lead->id);
        });
    }

    public function addNote(Lead $lead, string $body, string $type = 'note'): TimelineActivity
    {
        $entry = TimelineActivity::record($lead, $type, ucfirst($type).' added', $body);

        // Logging contact is what makes the recency component of the score move.
        if (in_array($type, ['call', 'email', 'meeting'], true)) {
            $lead->forceFill(['last_contacted_at' => now()])->saveQuietly();
            $this->scoring->apply($lead->refresh());
        }
        return $entry;
    }

    /**
     * Convert a Lead into an Account, a Contact, and optionally a Deal.
     *
     * Idempotent by refusal: a lead that already points at a customer throws rather
     * than silently creating a duplicate account.
     */
    public function convert(Lead $lead, array $options = []): array
    {
        if ($lead->isConverted()) {
            throw new RuntimeException('This lead has already been converted to customer #'.$lead->converted_to_customer_id.'.');
        }

        return DB::transaction(function () use ($lead, $options) {
            $accountMode = $options['account_mode'] ?? 'new';
            $accountReused = false;
            if ($accountMode === 'existing') {
                $customer = Customer::findOrFail((int) $options['account_id']);
            } elseif (!array_key_exists('account_mode', $options)
                && $lead->account_id && !($options['force_new_account'] ?? false)) {
                // The lead is already linked to an existing Account — convert into it.
                $customer = Customer::findOrFail($lead->account_id);
                $accountReused = true;
            } elseif (!($options['force_new_account'] ?? false)
                && ($match = $this->matchingAccountId($lead)) !== null) {
                // Dedup guard: a high-confidence existing Account (same email/tax_id, or ≥2 signals)
                // is reused instead of silently creating a second customer for the same company.
                // Pass force_new_account=true (or account_mode=existing) to override.
                $customer = Customer::findOrFail($match);
                $accountReused = true;
            } else {
                $legacyOverrides = array_filter([
                    'name' => $options['name'] ?? null,
                    'type' => $options['type'] ?? null,
                    'group_id' => $options['group_id'] ?? null,
                    'credit_limit' => $options['credit_limit'] ?? null,
                ], fn ($value) => $value !== null);
                $customer = $this->customers->create(array_merge([
                    'type'         => filled($lead->company_name) ? 'company' : 'individual',
                    'name'         => $lead->company_name ?: $lead->name,
                    'email'        => $lead->email,
                    'phone'        => $lead->phone,
                    'mobile'       => $lead->mobile,
                    'website'      => $lead->website,
                    'currency'     => $lead->currency,
                    'owner_id'     => $lead->owner_id,
                    'branch_id'    => $lead->branch_id,
                    'status'       => 'active',
                    'converted_from_lead_id' => $lead->id,
                ], $legacyOverrides, $options['account'] ?? []));
            }

            // Carry the lead's addresses over rather than losing them — only for a freshly created
            // account (reusing/existing accounts keep their own addresses).
            foreach ((!$accountReused && $accountMode === 'new') ? $lead->addresses : [] as $addr) {
                $customer->addresses()->create([
                    'company_id' => $customer->company_id,
                    'type' => $addr->type, 'label' => $addr->label,
                    'line1' => $addr->line1, 'line2' => $addr->line2,
                    'city' => $addr->city, 'state' => $addr->state,
                    'postal_code' => $addr->postal_code, 'country' => $addr->country,
                    'is_default' => $addr->is_default,
                ]);
            }

            $contact = null;
            $contactReused = false;
            if ($options['create_contact'] ?? true) {
                // The converted person is always represented as a Contact, including B2C/individual
                // Accounts. That keeps activities and Deal roles attached to a real person.
                // Dedup guard: if this account already has a contact matching the lead's email/phone,
                // reuse it (and make it primary) rather than inserting the same person twice.
                $existingContact = $this->matchingContact($customer, $lead);
                $customer->contacts()->update(['is_primary' => false]);
                if ($existingContact && !($options['force_new_contact'] ?? false)) {
                    $existingContact->forceFill(['is_primary' => true])->save();
                    $contact = $existingContact;
                    $contactReused = true;
                } else {
                    $contact = $customer->contacts()->create(array_merge([
                        'company_id' => $customer->company_id,
                        'name' => $lead->name, 'title' => $lead->title,
                        'email' => $lead->email, 'phone' => $lead->phone, 'mobile' => $lead->mobile,
                        'is_primary' => true,
                    ], $options['contact'] ?? [], ['is_primary' => true]));
                }
            }

            $deal = null;
            if ($options['create_deal'] ?? false) {
                $dealInput = $options['deal'] ?? [];
                // Pre-fill the opportunity with the lead's products of interest (unless the caller
                // supplied their own lines) — carries the trading intent straight into the quote.
                if (!isset($dealInput['products']) && $lead->products->isNotEmpty()) {
                    $dealInput['products'] = $lead->products->map(fn ($p) => [
                        'product_id' => $p->id, 'name' => $p->name,
                        'quantity' => $p->pivot->quantity ?? 1, 'unit_price' => (float) ($p->sale_price ?? 0),
                    ])->all();
                }
                $deal = $this->deals->create(array_merge([
                    'title' => ($lead->company_name ?: $lead->name).' Opportunity',
                    'customer_id' => $customer->id,
                    'lead_id' => $lead->id,
                    'owner_id' => $lead->owner_id,
                    'branch_id' => $lead->branch_id,
                    'amount' => $lead->estimated_value,
                    'currency' => $lead->currency,
                    'expected_close_date' => $lead->expected_close_date?->toDateString(),
                    'source' => $lead->source?->name,
                ], $dealInput, $contact ? [
                    'contacts' => [[
                        'contact_id' => $contact->id,
                        'role' => 'decision_maker',
                        'is_primary' => true,
                    ]],
                ] : []));
            }

            $wonStatusId = LeadStatus::where('is_won', true)->value('id');
            $lead->forceFill([
                'converted_to_customer_id' => $customer->id,
                'converted_at' => now(),
                'status_id' => $wonStatusId ?: $lead->status_id,
            ])->save();

            TimelineActivity::record($lead, 'system', "Lead converted to account {$customer->customer_no}",
                null, ['customer_id' => $customer->id, 'contact_id' => $contact?->id, 'deal_id' => $deal?->id]);
            TimelineActivity::record($customer, 'system', "Converted from lead {$lead->lead_no}",
                null, ['lead_id' => $lead->id]);

            $this->scoring->apply($lead->refresh());
            $this->workflows->fireEvent('leads', 'lead.converted', $lead);

            return [
                'customer' => $customer->refresh(),
                'contact' => $contact?->refresh(),
                'deal' => $deal,
                'account_reused' => $accountReused,
                'contact_reused' => $contactReused,
            ];
        });
    }

    /** Replace a lead's products-of-interest from a [{product_id, quantity?, note?}] list. */
    private function syncProducts(Lead $lead, array $products): void
    {
        $sync = [];
        foreach ($products as $p) {
            if (empty($p['product_id'])) continue;
            $sync[(int) $p['product_id']] = [
                'company_id' => $lead->company_id,
                'quantity' => $p['quantity'] ?? null,
                'note' => $p['note'] ?? null,
            ];
        }
        $lead->products()->sync($sync);
    }

    /** Highest-confidence existing Account for this lead, or null. Used to avoid duplicate customers
     *  on conversion. Reuses DuplicateDetectionService so the match logic stays in one place. */
    private function matchingAccountId(Lead $lead): ?int
    {
        $matches = $this->duplicates->check('account', [
            'name' => $lead->company_name ?: $lead->name,
            'company_name' => $lead->company_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'mobile' => $lead->mobile,
            'tax_id' => $lead->tax_id ?? null,
        ]);
        // Only auto-reuse on a HIGH-confidence match (email/tax_id, or ≥2 signals) — a lone
        // name/phone coincidence is too weak to merge two companies automatically.
        foreach ($matches as $m) {
            if (($m['confidence'] ?? null) === 'high') return (int) $m['id'];
        }
        return null;
    }

    /** An existing contact on this account matching the lead's email or phone, or null. */
    private function matchingContact(Customer $customer, Lead $lead): ?\App\Models\Contact
    {
        $email = trim((string) $lead->email);
        $phone = trim((string) ($lead->phone ?: $lead->mobile));
        if ($email === '' && $phone === '') return null;

        return $customer->contacts()
            ->where(function ($q) use ($email, $phone) {
                if ($email !== '') $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                if ($phone !== '') $q->orWhere('phone', $phone)->orWhere('mobile', $phone);
            })
            ->first();
    }

    /** First matching active rule by priority wins. */
    public function autoAssign(Lead $lead): ?int
    {
        $rules = LeadAssignmentRule::where('is_active', true)->orderBy('priority')->orderBy('id')->get();

        foreach ($rules as $rule) {
            if (!$rule->matches($lead)) continue;

            $userId = null;
            if ($rule->strategy === 'specific') {
                $userId = $rule->assign_to_user_id;
            } else {
                $pool = array_values($rule->round_robin_user_ids ?? []);
                if ($pool) {
                    $userId = $pool[$rule->round_robin_cursor % count($pool)];
                    // Cursor advances even on a repeat match, so the pool actually rotates.
                    $rule->forceFill(['round_robin_cursor' => $rule->round_robin_cursor + 1])->save();
                }
            }

            if ($userId) {
                $lead->forceFill(['owner_id' => $userId])->save();
                TimelineActivity::record($lead, 'system', "Auto-assigned by rule “{$rule->name}”");
                app(CrmNotifier::class)->leadAssigned($lead);
                return $userId;
            }
        }
        return null;
    }

    /**
     * Notify owners of open leads whose follow-up date has arrived. Idempotent: each scheduled
     * follow-up fires once (guarded by follow_up_notified_at), and re-fires only if the owner
     * later pushes follow_up_at to a new date. Runs from the scheduler in a console context, so
     * it steps outside the company global scope and stamps quietly (no audit/observer noise).
     */
    public function sweepFollowUpsDue(): int
    {
        $due = Lead::withoutGlobalScopes()
            ->whereNull('converted_to_customer_id')
            ->whereNotNull('owner_id')
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('follow_up_notified_at')
                  ->orWhereColumn('follow_up_notified_at', '<', 'follow_up_at');
            })
            ->get();

        $notifier = app(CrmNotifier::class);
        foreach ($due as $lead) {
            $notifier->leadFollowUpDue($lead);
            $lead->forceFill(['follow_up_notified_at' => now()])->saveQuietly();
        }
        return $due->count();
    }

    /** Sequential per-company lead number, e.g. LEAD-00042. */
    public function nextLeadNo(string $prefix = 'LEAD'): string
    {
        $companyId = auth()->user()?->company_id;

        $last = Lead::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)
            ->where('lead_no', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(lead_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('lead_no');

        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }

    public function stats(): array
    {
        return [
            'total'      => Lead::count(),
            'open'       => Lead::open()->count(),
            'converted'  => Lead::whereNotNull('converted_to_customer_id')->count(),
            'hot'        => Lead::open()->where('rating', 'hot')->count(),
            'warm'       => Lead::open()->where('rating', 'warm')->count(),
            'cold'       => Lead::open()->where('rating', 'cold')->count(),
            'mine'       => Lead::open()->where('owner_id', auth()->id())->count(),
            'unassigned' => Lead::open()->whereNull('owner_id')->count(),
            'pipeline_value' => (float) Lead::open()->sum('estimated_value'),
        ];
    }
}
