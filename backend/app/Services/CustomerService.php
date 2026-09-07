<?php
namespace App\Services;

use App\Models\Address;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Quotation;
use App\Models\Ticket;
use App\Models\TimelineActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Customer::query()
            // payment_terms_days must be selected or effectivePaymentTerms() silently reads null
            ->with(['group:id,name,code,payment_terms_days', 'owner:id,name'])
            ->withCount('contacts')
            ->status($filters['status'] ?? null)
            ->search($filters['q'] ?? null)
            ->when(!empty($filters['group_id']), fn ($q) => $q->where('group_id', $filters['group_id']))
            ->when(!empty($filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['owner_id']), function ($q) use ($filters) {
                return $filters['owner_id'] === 'me'
                    ? $q->where('owner_id', auth()->id())
                    : $q->where('owner_id', $filters['owner_id']);
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Customer-360 timeline: merge timeline_activities whose subject is the customer,
     * one of its contacts, or one of its deals. Newest first, paginated, tenant-safe.
     */
    public function timeline(Customer $customer, array $opts = []): LengthAwarePaginator
    {
        $perPage   = (int) ($opts['per_page'] ?? 30);
        $companyId = $customer->company_id;

        // Everything in this customer's 360, keyed by timeline subject_type.
        $projectIds = Project::where('customer_id', $customer->id)->pluck('id')->all();
        $subjects = [
            Customer::class    => [$customer->id],
            Contact::class     => $customer->contacts()->pluck('id')->all(),
            Deal::class        => Deal::where('customer_id', $customer->id)->pluck('id')->all(),
            Quotation::class   => Quotation::where('customer_id', $customer->id)->pluck('id')->all(),
            Invoice::class     => Invoice::where('customer_id', $customer->id)->pluck('id')->all(),
            Ticket::class      => Ticket::where('customer_id', $customer->id)->pluck('id')->all(),
            ProjectTask::class => $projectIds ? ProjectTask::whereIn('project_id', $projectIds)->pluck('id')->all() : [],
        ];

        $page = TimelineActivity::query()
            ->where('company_id', $companyId)
            ->where(function ($w) use ($subjects) {
                foreach ($subjects as $type => $ids) {
                    if (!empty($ids)) {
                        $w->orWhere(fn ($x) => $x->where('subject_type', $type)->whereIn('subject_id', $ids));
                    }
                }
            })
            ->when(!empty($opts['type']), fn ($q) => $q->where('type', $opts['type']))
            ->with(['user:id,name', 'subject'])
            ->orderByDesc('occurred_at')->orderByDesc('id')
            ->paginate($perPage);

        $page->getCollection()->transform(fn (TimelineActivity $t) => [
            'id'          => $t->id,
            'type'        => $t->type,
            'title'       => $t->title,
            'body'        => $t->body,
            'meta'        => $t->meta,
            'occurred_at' => optional($t->occurred_at)->toIso8601String(),
            'occurred_human' => optional($t->occurred_at)->diffForHumans(),
            'user'        => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name] : null,
            'source'      => [
                'type' => class_basename($t->subject_type),
                'id'   => $t->subject_id,
                'name' => $this->subjectLabel($t->subject),
            ],
        ]);
        return $page;
    }

    private function subjectLabel($s): ?string
    {
        if (!$s) return null;
        foreach (['name', 'title', 'subject'] as $f) {
            if (!empty($s->{$f})) return (string) $s->{$f};
        }
        $full = trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? ''));
        return $full ?: null;
    }

    public function find(int $id): Customer
    {
        return Customer::with([
            'group', 'owner:id,name', 'branch:id,name',
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('name'),
            'addresses',
            'timeline' => fn ($q) => $q->with('user:id,name')->orderByDesc('occurred_at')->limit(50),
        ])->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $addresses = $data['addresses'] ?? [];
            unset($data['addresses']);

            $data['customer_no'] ??= $this->nextCustomerNo();
            $data['owner_id'] ??= auth()->id();

            if (array_key_exists('custom_fields', $data)) {
                $data['custom_fields'] = app(CustomFieldService::class)->sanitize('customer', (array) $data['custom_fields']);
            }

            $customer = Customer::create($data);
            $this->syncAddresses($customer, $addresses);

            TimelineActivity::record($customer, 'system', 'Customer created');
            $this->workflows->fireEvent('customers', 'customer.created', $customer);

            return $this->find($customer->id);
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $addresses = $data['addresses'] ?? null;
            unset($data['addresses']);

            $before = $customer->status;

            if (array_key_exists('custom_fields', $data)) {
                // Merge onto existing values so a partial update doesn't wipe untouched fields.
                $data['custom_fields'] = array_merge($customer->custom_fields ?? [],
                    app(CustomFieldService::class)->sanitize('customer', (array) $data['custom_fields']));
            }

            $customer->update($data);

            if ($addresses !== null) $this->syncAddresses($customer, $addresses, replace: true);

            // Status transitions are the change people actually want to see in history.
            if (array_key_exists('status', $data) && $data['status'] !== $before) {
                TimelineActivity::record(
                    $customer, 'status_change',
                    "Status changed from {$before} to {$data['status']}",
                    null, ['from' => $before, 'to' => $data['status']],
                );
            }

            return $this->find($customer->id);
        });
    }

    public function addNote(Customer $customer, string $body, string $type = 'note'): TimelineActivity
    {
        return TimelineActivity::record($customer, $type, ucfirst($type).' added', $body);
    }

    /**
     * Sequential per-company customer number, e.g. CUST-00042.
     * Uses the current max rather than a counter table; good enough at this scale and
     * has no second source of truth to drift. Wrapped in the caller's transaction.
     */
    public function nextCustomerNo(string $prefix = 'CUST'): string
    {
        $companyId = auth()->user()?->company_id;

        $last = Customer::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('customer_no', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(customer_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('customer_no');

        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        return sprintf('%s-%05d', $prefix, $n);
    }

    public function stats(): array
    {
        return [
            'total'    => Customer::count(),
            'active'   => Customer::where('status', 'active')->count(),
            'on_hold'  => Customer::where('status', 'on_hold')->count(),
            'blocked'  => Customer::where('status', 'blocked')->count(),
            'mine'     => Customer::where('owner_id', auth()->id())->count(),
            'new_this_month' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /** @param array<int, array<string, mixed>> $addresses */
    private function syncAddresses(Customer $customer, array $addresses, bool $replace = false): void
    {
        if ($replace) $customer->addresses()->delete();

        foreach ($addresses as $row) {
            if (empty($row['line1'])) continue;
            $customer->addresses()->create([
                'company_id'  => $customer->company_id,
                'type'        => $row['type'] ?? 'billing',
                'label'       => $row['label'] ?? null,
                'line1'       => $row['line1'],
                'line2'       => $row['line2'] ?? null,
                'city'        => $row['city'] ?? null,
                'state'       => $row['state'] ?? null,
                'postal_code' => $row['postal_code'] ?? null,
                'country'     => $row['country'] ?? null,
                'is_default'  => (bool) ($row['is_default'] ?? false),
            ]);
        }
    }
}
