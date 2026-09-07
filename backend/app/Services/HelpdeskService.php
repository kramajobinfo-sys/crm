<?php
namespace App\Services;

use App\Models\Escalation;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketRoutingRule;
use App\Models\TimelineActivity;
use App\Notifications\SlaBreachNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class HelpdeskService
{
    public function __construct(private readonly WorkflowService $workflows) {}

    public function paginate(array $f = [], int $perPage = 25): LengthAwarePaginator
    {
        return Ticket::query()
            ->with(['category:id,name', 'assignee:id,name', 'customer:id,name,customer_no', 'slaPolicy:id,name'])
            ->search($f['q'] ?? null)
            ->when(($f['status'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(!empty($f['status']) && !in_array($f['status'], ['all', 'open'], true), fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['priority']), fn ($q) => $q->where('priority', $f['priority']))
            ->when(!empty($f['category_id']), fn ($q) => $q->where('category_id', $f['category_id']))
            ->when(!empty($f['assigned_to']), function ($q) use ($f) {
                return $f['assigned_to'] === 'me' ? $q->where('assigned_to', auth()->id())
                    : ($f['assigned_to'] === 'unassigned' ? $q->whereNull('assigned_to') : $q->where('assigned_to', $f['assigned_to']));
            })
            ->when(($f['breaching'] ?? null) === '1', fn ($q) => $q->open()->where('due_at', '<', now()))
            ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function find(int $id): Ticket
    {
        return Ticket::with([
            'category:id,name', 'customer:id,name,customer_no', 'assignee:id,name', 'creator:id,name',
            'slaPolicy', 'replies' => fn ($q) => $q->with('user:id,name'),
            'escalations' => fn ($q) => $q->with(['assignee:id,name', 'escalator:id,name']),
        ])->findOrFail($id);
    }

    public function create(array $data): Ticket
    {
        return DB::transaction(function () use ($data) {
            $data['ticket_no'] ??= $this->nextTicketNo('TKT', $data['company_id'] ?? null);
            $data['created_by'] ??= auth()->id();
            $data['status'] = 'new';
            $ticket = Ticket::create($data);
            // refresh() hydrates the DB column defaults (priority 'medium', channel 'manual').
            // Without it, a request that omits `priority` — which is every portal ticket, since
            // the portal UI posts only {subject, description} — left $ticket->priority NULL in
            // memory. applySla then matched whereNull('priority') against a NOT NULL column,
            // found no policy, and wrote sla_policy_id/first_response_due_at/due_at as NULL,
            // permanently: the ticket never appeared in the `breaching` filter, never counted in
            // stats, and never reached the SLA-breach dashboard KPI. The same missing default
            // also produced the reply body "Ticket created via ." just below.
            $ticket->refresh();
            $this->applySla($ticket);
            if (!$ticket->assigned_to) $this->autoRoute($ticket);
            TicketReply::create([
                'company_id' => $ticket->company_id, 'ticket_id' => $ticket->id,
                'user_id' => auth()->id(), 'author_type' => 'system',
                'is_internal' => true, 'body' => 'Ticket created via '.$ticket->channel.'.',
            ]);
            $this->workflows->fireEvent('tickets', 'ticket.created', $ticket);
            \App\Models\TimelineActivity::record($ticket, 'system', 'Ticket created');
            return $this->find($ticket->id);
        });
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $data) {
            $priorityChanged = array_key_exists('priority', $data) && $data['priority'] !== $ticket->priority;
            $ticket->update($data);
            // Re-derive SLA deadlines if the priority (and thus policy) changed.
            if ($priorityChanged) $this->applySla($ticket->refresh());
            return $this->find($ticket->id);
        });
    }

    public function reply(Ticket $ticket, string $body, bool $internal, string $authorType = 'agent'): TicketReply
    {
        return DB::transaction(function () use ($ticket, $body, $internal, $authorType) {
            $reply = TicketReply::create([
                'company_id' => $ticket->company_id, 'ticket_id' => $ticket->id,
                'user_id' => $authorType === 'agent' ? auth()->id() : null,
                'author_type' => $authorType, 'is_internal' => $internal, 'body' => $body,
            ]);

            $patch = [];
            // First public agent response stops the first-response clock.
            if ($authorType === 'agent' && !$internal && !$ticket->first_response_at) {
                $patch['first_response_at'] = now();
            }
            // A public agent reply on a brand-new ticket opens it; a customer reply on a
            // resolved ticket reopens it.
            if ($authorType === 'agent' && !$internal && $ticket->status === 'new') {
                $patch['status'] = 'open';
            }
            if ($authorType === 'customer' && in_array($ticket->status, ['resolved', 'closed'], true)) {
                $patch['status'] = 'open';
                $patch['resolved_at'] = null; $patch['closed_at'] = null;
                $patch['reopened_count'] = $ticket->reopened_count + 1;
            }
            if ($patch) $ticket->forceFill($patch)->save();

            return $reply->load('user:id,name');
        });
    }

    public function assign(Ticket $ticket, ?int $userId): Ticket
    {
        $patch = ['assigned_to' => $userId];
        if ($userId && $ticket->status === 'new') $patch['status'] = 'open';
        $ticket->forceFill($patch)->save();
        return $this->find($ticket->id);
    }

    /** Auto-assign an unassigned ticket via the first matching routing rule. Returns the user id, or null. */
    public function autoRoute(Ticket $ticket): ?int
    {
        $rules = TicketRoutingRule::where('company_id', $ticket->company_id)
            ->where('is_active', true)->orderBy('priority')->orderBy('id')->get();

        foreach ($rules as $rule) {
            if (!$rule->matches($ticket)) continue;
            $userId = $this->resolveAssignee($rule, $ticket);
            if ($userId) {
                $patch = ['assigned_to' => $userId];
                if ($ticket->status === 'new') $patch['status'] = 'open';
                $ticket->forceFill($patch)->save();
                \App\Models\TimelineActivity::record($ticket, 'system', "Auto-routed by rule \u{201C}{$rule->name}\u{201D}");
                return $userId;
            }
        }
        return null;
    }

    private function resolveAssignee(TicketRoutingRule $rule, Ticket $ticket): ?int
    {
        if ($rule->strategy === 'specific') return $rule->assign_to_user_id;

        $pool = array_values(array_filter(array_map('intval', $rule->pool_user_ids ?? [])));
        if (!$pool) return null;

        if ($rule->strategy === 'round_robin') {
            $userId = $pool[$rule->round_robin_cursor % count($pool)];
            $rule->forceFill(['round_robin_cursor' => $rule->round_robin_cursor + 1])->save();
            return $userId;
        }

        // least_busy — fewest OPEN tickets among the pool; ties broken by lowest user id.
        $counts = [];
        foreach ($pool as $uid) {
            $counts[$uid] = Ticket::withoutGlobalScopes()
                ->where('company_id', $ticket->company_id)->open()
                ->where('assigned_to', $uid)->count();
        }
        $min = min($counts);
        $tied = array_keys(array_filter($counts, fn ($c) => $c === $min));
        sort($tied);
        return $tied[0] ?? null;
    }

    public function setStatus(Ticket $ticket, string $status): Ticket
    {
        $changed = $ticket->status !== $status;
        $patch = ['status' => $status];
        $patch['resolved_at'] = $status === 'resolved' ? ($ticket->resolved_at ?? now()) : ($status === 'closed' ? $ticket->resolved_at : null);
        $patch['closed_at'] = $status === 'closed' ? now() : null;
        if (in_array($ticket->status, ['resolved', 'closed'], true) && in_array($status, ['open', 'pending'], true)) {
            $patch['reopened_count'] = $ticket->reopened_count + 1;
        }
        $ticket->forceFill($patch)->save();
        if ($changed) { $this->workflows->fireEvent('tickets', 'ticket.status_changed', $ticket); \App\Models\TimelineActivity::record($ticket, 'status_change', 'Ticket '.$status, null, ['status' => $status]); }
        return $this->find($ticket->id);
    }

    public function escalate(Ticket $ticket, ?int $toUserId, ?string $reason, ?string $note): Ticket
    {
        return DB::transaction(function () use ($ticket, $toUserId, $reason, $note) {
            $level = ($ticket->escalations()->max('level') ?? 0) + 1;
            Escalation::create([
                'company_id' => $ticket->company_id, 'ticket_id' => $ticket->id, 'level' => $level,
                'reason' => $reason, 'escalated_to' => $toUserId, 'escalated_by' => auth()->id(),
                'note' => $note, 'escalated_at' => now(),
            ]);
            $patch = [];
            if ($toUserId) $patch['assigned_to'] = $toUserId;
            // Bump priority one notch on escalation, capped at urgent.
            $ladder = ['low' => 'medium', 'medium' => 'high', 'high' => 'urgent', 'urgent' => 'urgent'];
            // Captured BEFORE the write: save() calls syncOriginal(), so comparing against
            // getOriginal('priority') afterwards compared the new value with itself — always
            // false, so the SLA was never recomputed and an escalated ticket kept the
            // deadlines of its old priority.
            $previousPriority = $ticket->priority;
            $patch['priority'] = $ladder[$ticket->priority] ?? $ticket->priority;
            $ticket->forceFill($patch)->save();
            if ($patch['priority'] !== $previousPriority) $this->applySla($ticket->refresh());
            $this->workflows->fireEvent('tickets', 'ticket.escalated', $ticket);
            return $this->find($ticket->id);
        });
    }

    /** Pick the active SLA policy for the ticket's priority and stamp response/resolution deadlines. */
    public function applySla(Ticket $ticket): void
    {
        // company_id explicitly: this is reachable from the customer portal, where the request
        // authenticates on the `portal` guard and BelongsToCompany's global scope (which checks
        // the DEFAULT guard) adds nothing — so an unscoped lookup returned the lowest-id active
        // policy across ALL tenants, stamping another company's deadlines onto this ticket. The
        // same filter also covers platform admins, whom the scope deliberately skips.
        $policy = SlaPolicy::where('company_id', $ticket->company_id)
            ->where('is_active', true)->where('priority', $ticket->priority)->first();
        if (!$policy) {
            $ticket->forceFill(['sla_policy_id' => null, 'first_response_due_at' => null, 'due_at' => null,
                'response_breached_at' => null, 'sla_breached_at' => null])->save();
            return;
        }
        $base = $ticket->created_at ?? now();
        // Recomputing deadlines clears the breach markers so a re-breach after a change is detectable.
        $ticket->forceFill([
            'sla_policy_id' => $policy->id,
            'first_response_due_at' => $base->copy()->addMinutes($policy->first_response_minutes),
            'due_at' => $base->copy()->addMinutes($policy->resolution_minutes),
            'response_breached_at' => null, 'sla_breached_at' => null,
        ])->save();
    }

    /**
     * Detect newly-breached SLAs (first-response and resolution) and act once per breach: stamp the
     * marker, log a timeline entry, fire the `ticket.sla_breached` workflow event, and notify the
     * assignee. Runs cross-tenant from the scheduler (no auth → global company scope is inert).
     *
     * @return array{response:int,resolution:int}
     */
    public function sweepSlaBreaches(): array
    {
        $counts = ['response' => 0, 'resolution' => 0];

        // First-response breaches: past the response deadline, no agent reply yet, not already flagged.
        Ticket::open()
            ->whereNotNull('first_response_due_at')->where('first_response_due_at', '<', now())
            ->whereNull('first_response_at')->whereNull('response_breached_at')
            ->with('assignee:id')->chunkById(200, function ($tickets) use (&$counts) {
                foreach ($tickets as $ticket) {
                    $ticket->forceFill(['response_breached_at' => now()])->save();
                    $this->flagBreach($ticket, 'response');
                    $counts['response']++;
                }
            });

        // Resolution breaches: past the resolution deadline, still open, not already flagged.
        Ticket::open()
            ->whereNotNull('due_at')->where('due_at', '<', now())->whereNull('sla_breached_at')
            ->with('assignee:id')->chunkById(200, function ($tickets) use (&$counts) {
                foreach ($tickets as $ticket) {
                    $ticket->forceFill(['sla_breached_at' => now()])->save();
                    $this->flagBreach($ticket, 'resolution');
                    $counts['resolution']++;
                }
            });

        return $counts;
    }

    private function flagBreach(Ticket $ticket, string $kind): void
    {
        $label = $kind === 'response' ? 'First-response SLA breached' : 'Resolution SLA breached';
        TimelineActivity::record($ticket, 'system', $label, null, ['kind' => $kind]);
        $this->workflows->fireEvent('tickets', 'ticket.sla_breached', $ticket);
        if ($ticket->assignee) {
            $ticket->assignee->notify(new SlaBreachNotification($ticket, $kind));
        }
    }

    public function stats(): array
    {
        return [
            'open'         => Ticket::open()->count(),
            'unassigned'   => Ticket::open()->whereNull('assigned_to')->count(),
            'mine'         => Ticket::open()->where('assigned_to', auth()->id())->count(),
            'breaching'    => Ticket::open()->where('due_at', '<', now())->count(),
            'urgent'       => Ticket::open()->where('priority', 'urgent')->count(),
            'resolved_today' => Ticket::whereDate('resolved_at', now()->toDateString())->count(),
        ];
    }

    public function nextTicketNo(string $prefix = 'TKT', ?int $companyId = null): string
    {
        $companyId ??= auth()->user()?->company_id;
        $last = Ticket::withoutGlobalScopes()->withTrashed()
            ->where('company_id', $companyId)->where('ticket_no', 'like', $prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(ticket_no, ?) AS UNSIGNED) DESC', [strlen($prefix) + 2])
            ->value('ticket_no');
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;
        return sprintf('%s-%05d', $prefix, $n);
    }
}
