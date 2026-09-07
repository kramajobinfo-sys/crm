<?php
namespace App\Services;

use App\Models\Deal;
use App\Models\DealProduct;
use App\Models\Contact;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\TimelineActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DealService
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Deal::query()
            ->with(['stage:id,name,color,is_won,is_lost', 'pipeline:id,name', 'owner:id,name', 'customer:id,name,customer_no'])
            ->search($filters['q'] ?? null)
            ->when(!empty($filters['pipeline_id']), fn ($q) => $q->where('pipeline_id', $filters['pipeline_id']))
            ->when(!empty($filters['stage_id']), fn ($q) => $q->where('stage_id', $filters['stage_id']))
            ->when(!empty($filters['status']) && $filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->when(!empty($filters['owner_id']), function ($q) use ($filters) {
                return $filters['owner_id'] === 'me'
                    ? $q->where('owner_id', auth()->id())
                    : $q->where('owner_id', $filters['owner_id']);
            })
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** Kanban view: every non-terminal stage of a pipeline with its open deals. */
    public function board(int $pipelineId, array $filters = []): array
    {
        $pipeline = Pipeline::findOrFail($pipelineId);
        $stages = $pipeline->stages()->get();

        $ownerId = $filters['owner_id'] ?? null;

        // One query per stage (the 100-row cap is per column, so it has to stay per stage),
        // but the relations are eager-loaded ONCE across the whole board. Previously the
        // with() sat inside this loop, so a 6-stage pipeline issued 6 owner queries and 6
        // customer queries instead of 1 each — 25 queries total, growing with stage count.
        $dealsByStage = [];
        foreach ($stages as $stage) {
            $dealsByStage[$stage->id] = Deal::query()
                ->where('stage_id', $stage->id)
                ->when($ownerId === 'me', fn ($q) => $q->where('owner_id', auth()->id()))
                ->when($ownerId && $ownerId !== 'me', fn ($q) => $q->where('owner_id', $ownerId))
                ->orderByDesc('id')->limit(100)->get();
        }

        // Collection::load() populates the relation on the same model instances the
        // per-stage collections hold, so this covers every column in two queries.
        (new EloquentCollection(array_merge([], ...array_map(
            fn ($c) => $c->all(), array_values($dealsByStage)
        ))))->load(['owner:id,name', 'customer:id,name,customer_no']);

        $columns = $stages->map(function (PipelineStage $stage) use ($dealsByStage) {
            $deals = $dealsByStage[$stage->id];

            return [
                'stage'       => [
                    'id' => $stage->id, 'name' => $stage->name, 'code' => $stage->code,
                    'color' => $stage->color, 'is_won' => $stage->is_won, 'is_lost' => $stage->is_lost,
                    'probability' => $stage->probability,
                    'required_fields' => $stage->required_fields ?? [],
                    'allowed_next_stage_ids' => $stage->allowed_next_stage_ids ?? [],
                ],
                'count'       => $deals->count(),
                'total_value' => (float) $deals->sum('amount'),
                'deals'       => $deals->map(fn (Deal $d) => [
                    'id' => $d->id, 'deal_no' => $d->deal_no, 'title' => $d->title,
                    'amount' => (float) $d->amount, 'currency' => $d->currency,
                    'probability' => $d->probability, 'status' => $d->status,
                    'expected_close_date' => $d->expected_close_date?->toDateString(),
                    'owner' => $d->owner ? ['id' => $d->owner->id, 'name' => $d->owner->name] : null,
                    'customer' => $d->customer ? ['id' => $d->customer->id, 'name' => $d->customer->name] : null,
                ])->all(),
            ];
        })->all();

        return [
            'pipeline' => ['id' => $pipeline->id, 'name' => $pipeline->name],
            'columns'  => $columns,
        ];
    }

    public function find(int $id): Deal
    {
        return Deal::with([
            'pipeline:id,name', 'stage', 'owner:id,name', 'branch:id,name',
            'customer:id,name,customer_no', 'lead:id,name,lead_no', 'lostReason:id,name',
            'project:id,deal_id,project_no,name,status',
            'products',
            'contacts',
            'attachments' => fn ($q) => $q->with('uploader:id,name')->latest(),
            'timeline' => fn ($q) => $q->with('user:id,name')->orderByDesc('occurred_at')->limit(50),
        ])->findOrFail($id);
    }

    public function create(array $data): Deal
    {
        return DB::transaction(function () use ($data) {
            $products = $data['products'] ?? null;
            $contacts = $data['contacts'] ?? null;
            unset($data['products'], $data['contacts']);

            $data['deal_no'] ??= $this->nextDealNo();
            $stage = $this->resolveStage($data);
            $this->enforceStageRequirements($stage, $data);
            $data['stage_id']    = $stage->id;
            $data['pipeline_id'] = $stage->pipeline_id;
            $data['status']      = $stage->impliedStatus();
            $data['probability'] ??= $stage->probability;
            $this->stampTerminal($data, $stage);

            if (array_key_exists('custom_fields', $data)) {
                $data['custom_fields'] = app(CustomFieldService::class)->sanitize('deal', (array) $data['custom_fields']);
            }

            $deal = Deal::create($data);

            if (is_array($products)) $this->syncProducts($deal, $products);
            if (is_array($contacts)) $this->syncContacts($deal, $contacts);

            TimelineActivity::record($deal, 'system', 'Deal created');
            $this->workflows->fireEvent('deals', 'deal.created', $deal);
            return $this->find($deal->id);
        });
    }

    public function update(Deal $deal, array $data): Deal
    {
        return DB::transaction(function () use ($deal, $data) {
            $products = $data['products'] ?? null;
            $contactsProvided = array_key_exists('contacts', $data);
            $contacts = $data['contacts'] ?? null;
            $accountChanged = array_key_exists('customer_id', $data)
                && (int) ($data['customer_id'] ?? 0) !== (int) ($deal->customer_id ?? 0);
            unset($data['products'], $data['contacts']);

            $beforeStage = $deal->stage_id;

            // A stage change drives status and the won/lost timestamps.
            if (!empty($data['stage_id']) && (int) $data['stage_id'] !== $beforeStage) {
                $stage = PipelineStage::findOrFail($data['stage_id']);
                $fromStage = PipelineStage::find($beforeStage);
                if ($fromStage) $this->enforceTransition($fromStage, $stage);
                $this->enforceStageRequirements($stage, $data, $deal);
                $data['pipeline_id'] = $stage->pipeline_id;
                $data['status'] = $stage->impliedStatus();
                $this->stampTerminal($data, $stage);
            }

            if (array_key_exists('custom_fields', $data)) {
                // Merge onto existing values so a partial update doesn't wipe untouched fields.
                $data['custom_fields'] = array_merge($deal->custom_fields ?? [],
                    app(CustomFieldService::class)->sanitize('deal', (array) $data['custom_fields']));
            }

            $deal->update($data);

            if (!empty($data['stage_id']) && (int) $data['stage_id'] !== $beforeStage) {
                $from = PipelineStage::find($beforeStage)?->name ?? '—';
                $to   = PipelineStage::find($data['stage_id'])?->name ?? '—';
                TimelineActivity::record($deal, 'status_change', "Stage moved from {$from} to {$to}",
                    null, ['from' => $beforeStage, 'to' => (int) $data['stage_id']]);
                $this->workflows->fireEvent('deals', 'deal.stage_changed', $deal);
                if ($stage->is_won) $this->workflows->fireEvent('deals', 'deal.won', $deal);
                if ($stage->is_lost) $this->workflows->fireEvent('deals', 'deal.lost', $deal);
            }

            if (is_array($products)) $this->syncProducts($deal, $products);
            if ($contactsProvided) {
                $this->syncContacts($deal, is_array($contacts) ? $contacts : []);
            } elseif ($accountChanged) {
                // Contact roles belong to an Account context. Never leave stale people attached
                // after the Deal is moved to another Account (or made account-less).
                $deal->contacts()->detach();
            }

            return $this->find($deal->id);
        });
    }

    /** Move a deal to another stage (kanban drag). Reuses update() so timeline + status stay consistent. */
    public function moveStage(Deal $deal, int $stageId): Deal
    {
        $stage = PipelineStage::findOrFail($stageId);
        if ($stage->pipeline_id !== $deal->pipeline_id) {
            throw new RuntimeException('That stage belongs to a different pipeline.');
        }
        return $this->update($deal, ['stage_id' => $stageId]);
    }

    /** Mark a deal lost, recording why, and drop it into the pipeline's lost stage. */
    public function markLost(Deal $deal, ?int $lostReasonId, ?string $note = null): Deal
    {
        $lostStage = PipelineStage::where('pipeline_id', $deal->pipeline_id)->where('is_lost', true)->first();
        return DB::transaction(function () use ($deal, $lostReasonId, $note, $lostStage) {
            $deal = $this->update($deal, array_filter([
                'stage_id'       => $lostStage?->id,
                'lost_reason_id' => $lostReasonId,
            ], fn ($v) => $v !== null));
            $deal->forceFill(['status' => 'lost', 'lost_at' => now(), 'lost_reason_id' => $lostReasonId])->save();
            TimelineActivity::record($deal, 'system', 'Deal marked lost', $note);
            return $this->find($deal->id);
        });
    }

    public function addNote(Deal $deal, string $body, string $type = 'note'): TimelineActivity
    {
        return TimelineActivity::record($deal, $type, ucfirst($type).' added', $body);
    }

    /** Replace the deal's line items and roll the total up into deals.amount. */
    public function syncProducts(Deal $deal, array $lines): void
    {
        $deal->products()->delete();
        $total = 0.0;
        foreach (array_values($lines) as $i => $line) {
            $qty   = (float) ($line['quantity'] ?? 1);
            $price = (float) ($line['unit_price'] ?? 0);
            $disc  = (float) ($line['discount_pct'] ?? 0);
            $lineTotal = DealProduct::compute($qty, $price, $disc);
            $total += $lineTotal;

            $deal->products()->create([
                'company_id'   => $deal->company_id,
                'product_id'   => $line['product_id'] ?? null,
                'name'         => $line['name'] ?? 'Item',
                'description'  => $line['description'] ?? null,
                'quantity'     => $qty,
                'unit_price'   => $price,
                'discount_pct' => $disc,
                'line_total'   => $lineTotal,
                'sort_order'   => $i,
            ]);
        }
        // Line items are authoritative once present.
        $deal->forceFill(['amount' => round($total, 2)])->save();
    }

    /** Replace the people participating in a Deal, including their buying role. */
    public function syncContacts(Deal $deal, array $rows): void
    {
        if (!$rows) {
            $deal->contacts()->detach();
            return;
        }
        if (!$deal->customer_id) {
            throw new RuntimeException('Select an Account before adding Deal Contacts.');
        }
        if (collect($rows)->filter(fn ($row) => (bool) ($row['is_primary'] ?? false))->count() > 1) {
            throw new RuntimeException('Only one Deal Contact may be primary.');
        }

        $ids = collect($rows)->pluck('contact_id')->map(fn ($id) => (int) $id)->unique()->values();
        $validIds = Contact::query()
            ->where('customer_id', $deal->customer_id)
            ->whereIn('id', $ids)
            ->pluck('id');
        if ($validIds->count() !== $ids->count()) {
            throw new RuntimeException('Every Deal Contact must belong to the selected Account.');
        }

        $sync = [];
        foreach ($rows as $row) {
            $sync[(int) $row['contact_id']] = [
                'company_id' => $deal->company_id,
                'role' => $row['role'] ?? 'other',
                'is_primary' => (bool) ($row['is_primary'] ?? false),
            ];
        }
        $deal->contacts()->sync($sync);
    }

    /** Sequential per-company deal number, e.g. DEAL-00042. */
    public function nextDealNo(string $prefix = 'DEAL'): string
    {
        $companyId = auth()->user()?->company_id;
        $last = Deal::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)
            ->where('deal_no', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(deal_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('deal_no');
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }

    public function stats(): array
    {
        $lostStageIds = PipelineStage::where('is_won', false)->where('is_lost', false)->pluck('id');
        return [
            'open'         => Deal::open()->count(),
            'won'          => Deal::won()->count(),
            'lost'         => Deal::lost()->count(),
            'mine'         => Deal::open()->where('owner_id', auth()->id())->count(),
            'open_value'   => (float) Deal::open()->sum('amount'),
            'won_value'    => (float) Deal::won()->whereMonth('won_at', now()->month)->whereYear('won_at', now()->year)->sum('amount'),
            // Summed in SQL rather than hydrating every open deal. ROUND is applied per row
            // to match Deal::getWeightedAmountAttribute() exactly.
            'weighted_value' => (float) Deal::open()->sum(DB::raw('ROUND(amount * probability / 100, 2)')),
            'win_rate'     => $this->winRate(),
        ];
    }

    private function winRate(): ?float
    {
        $closed = Deal::whereIn('status', ['won', 'lost'])->count();
        if ($closed === 0) return null;
        return round(Deal::won()->count() / $closed * 100, 1);
    }

    /** Resolve which stage a new deal starts in: explicit stage, else first stage of the pipeline. */
    private function resolveStage(array $data): PipelineStage
    {
        if (!empty($data['stage_id'])) {
            return PipelineStage::findOrFail($data['stage_id']);
        }
        $pipelineId = $data['pipeline_id'] ?? Pipeline::where('is_default', true)->value('id') ?? Pipeline::value('id');
        $stage = PipelineStage::where('pipeline_id', $pipelineId)->orderBy('order_index')->first();
        if (!$stage) throw new RuntimeException('The selected pipeline has no stages configured.');
        return $stage;
    }

    /** Keep won_at/lost_at in step with the terminal-ness of the target stage. */
    private function stampTerminal(array &$data, PipelineStage $stage): void
    {
        $data['won_at']  = $stage->is_won ? now() : null;
        $data['lost_at'] = $stage->is_lost ? now() : null;
        if ($stage->is_won) $data['probability'] = 100;
        if ($stage->is_lost) $data['probability'] = 0;
    }

    /**
     * Blueprint: reject a stage-to-stage move the target stage's `allowed_next_stage_ids` doesn't
     * list. Marking a deal lost is always allowed regardless of configured sequence — losing isn't
     * a forward process step, so it would be a usability trap to require every stage's admin to
     * explicitly whitelist the lost stage just so `markLost()` keeps working.
     */
    private function enforceTransition(PipelineStage $from, PipelineStage $to): void
    {
        if ($to->is_lost) return;
        $allowed = $from->allowed_next_stage_ids;
        if (!empty($allowed) && !in_array($to->id, $allowed, true)) {
            throw new RuntimeException("Blueprint: \"{$from->name}\" cannot move directly to \"{$to->name}\".");
        }
    }

    /** Blueprint: reject entering a stage while any of its required fields are still empty. */
    private function enforceStageRequirements(PipelineStage $stage, array $data, ?Deal $deal = null): void
    {
        $missing = [];
        foreach ($stage->required_fields ?? [] as $field) {
            $value = array_key_exists($field, $data) ? $data[$field] : $deal?->{$field};
            if ($value === null || $value === '') $missing[] = $field;
        }
        if ($missing) {
            throw new RuntimeException('Blueprint: "'.$stage->name.'" requires: '.implode(', ', $missing).'.');
        }
    }
}
