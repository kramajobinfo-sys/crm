<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WorkflowService
{
    public function __construct(private readonly WebhookDispatcher $webhooks) {}

    /** entity key → model class. */
    private const ENTITY_MODELS = [
        'leads' => Lead::class, 'deals' => Deal::class, 'tickets' => Ticket::class,
        'customers' => Customer::class, 'invoices' => Invoice::class, 'quotations' => Quotation::class,
    ];

    /**
     * Fields an `update_field` action may set, per entity (kept tight for safety).
     *
     * `deals.status` and `invoices.status` are deliberately NOT here. Both are DERIVED —
     * a deal's status comes from its stage (`PipelineStage::impliedStatus()`), an invoice's
     * from its payments (`SalesService::recalcInvoice`). Setting them directly is what
     * produced a deal showing Won on the Kanban while `Deal::open()` still counted it, and
     * an invoice reading `paid` with `amount_paid` untouched. Change a deal's state via
     * `stage_id` (which this action delegates to DealService) and an invoice's by recording
     * a payment.
     */
    private const UPDATABLE = [
        'leads' => ['status_id', 'rating', 'owner_id', 'score'],
        'deals' => ['stage_id', 'owner_id', 'probability'],
        'tickets' => ['status', 'priority', 'assigned_to'],
        'customers' => ['status', 'owner_id'],
        'invoices' => [],
        'quotations' => ['status', 'owner_id'],
    ];

    /**
     * Re-entrancy guard. Actions now delegate to the owning services, and those fire their
     * own events — so without this a workflow triggered by `deal.stage_changed` whose action
     * moves the stage would recurse until the stack blew. A workflow's own actions therefore
     * never trigger further workflows: cascades are refused rather than depth-limited,
     * because a partially-applied cascade is worse than none.
     */
    private static bool $running = false;

    public function paginate(array $f = [], int $perPage = 25)
    {
        return Workflow::query()->with('creator:id,name')->withCount('actions')
            ->when(!empty($f['q']), fn ($q) => $q->where('name', 'like', '%'.$f['q'].'%'))
            ->when(!empty($f['entity']), fn ($q) => $q->where('entity', $f['entity']))
            ->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): Workflow
    {
        return Workflow::with(['actions', 'creator:id,name',
            'runs' => fn ($q) => $q->with('triggeredBy:id,name')->limit(15)])->findOrFail($id);
    }

    public function create(array $data): Workflow
    {
        return DB::transaction(function () use ($data) {
            $actions = $data['actions'] ?? [];
            unset($data['actions']);
            $data['created_by'] ??= auth()->id();
            $workflow = Workflow::create($data);
            $this->syncActions($workflow, $actions);
            return $this->find($workflow->id);
        });
    }

    public function update(Workflow $workflow, array $data): Workflow
    {
        return DB::transaction(function () use ($workflow, $data) {
            $actions = $data['actions'] ?? null;
            unset($data['actions']);
            $workflow->update($data);
            if (is_array($actions)) $this->syncActions($workflow, $actions);
            return $this->find($workflow->id);
        });
    }

    private function syncActions(Workflow $workflow, array $actions): void
    {
        $workflow->actions()->delete();
        foreach (array_values($actions) as $i => $a) {
            $workflow->actions()->create([
                'company_id' => $workflow->company_id,
                'order' => $i, 'type' => $a['type'], 'config' => $a['config'] ?? [],
            ]);
        }
    }

    /**
     * Run a workflow, optionally against a subject record. Conditions are evaluated against the
     * subject; if they fail the run is logged as skipped. Each action's result is logged.
     */
    public function run(Workflow $workflow, ?string $entityId = null, string $triggerType = 'manual'): WorkflowRun
    {
        $subject = $entityId ? $this->resolveSubject($workflow->entity, (int) $entityId) : null;

        $started = now();
        $conditions = $workflow->conditions ?? [];

        if ($conditions) {
            // Fail CLOSED with no subject. The guard used to be `if ($subject && !pass())`,
            // so a workflow with conditions and no subject — every schedule trigger, and any
            // manual run without a subject_id — skipped the check entirely and ran
            // unconditionally, while the UI kept displaying the conditions as if live. A
            // daily-cron workflow with `status = lost` fired its actions every single day.
            if (!$subject) {
                return $this->recordRun($workflow, null, $triggerType, 'skipped', [[
                    'type' => 'conditions', 'status' => 'skipped',
                    'message' => 'Conditions cannot be evaluated without a subject record '
                        .'(schedule triggers have none). Remove the conditions or use an event trigger.',
                ]], 0, $started);
            }
            if (!$this->conditionsPass($conditions, $subject)) {
                return $this->recordRun($workflow, $subject, $triggerType, 'skipped', [
                    ['type' => 'conditions', 'status' => 'skipped', 'message' => 'Conditions not met.'],
                ], 0, $started);
            }
        }

        $log = []; $ran = 0; $failed = 0;
        $outermost = !self::$running;
        self::$running = true;
        try {
            foreach ($workflow->actions as $action) {
                try {
                    $message = $this->execute($action->type, $action->config ?? [], $subject, $workflow);
                    $log[] = ['type' => $action->type, 'status' => 'success', 'message' => $message];
                    $ran++;
                } catch (\Throwable $e) {
                    $log[] = ['type' => $action->type, 'status' => 'failed', 'message' => $e->getMessage()];
                    $failed++;
                }
            }
        } finally {
            // Only the outermost run clears it, so a nested attempt cannot re-open the gate.
            if ($outermost) self::$running = false;
        }

        $status = $failed === 0 ? 'success' : ($ran > 0 ? 'partial' : 'failed');
        return $this->recordRun($workflow, $subject, $triggerType, $status, $log, $ran, $started);
    }

    /**
     * Fire an event trigger for one entity's subject record. Runs every active, matching
     * event-workflow in the subject's own company. Called synchronously from the owning
     * service right after the real transition, so a workflow failure must never surface as
     * the triggering request's failure — each run is isolated and logged, not rethrown.
     */
    public function fireEvent(string $entity, string $event, Model $subject): void
    {
        // A workflow's own actions must not trigger further workflows — see self::$running.
        if (self::$running) return;

        $workflows = Workflow::where('company_id', $subject->company_id)
            ->where('entity', $entity)->where('trigger_type', 'event')
            ->where('trigger_event', $event)->where('is_active', true)
            ->with('actions')->get();

        foreach ($workflows as $workflow) {
            try {
                $this->run($workflow, (string) $subject->getKey(), 'event');
            } catch (\Throwable $e) {
                Log::warning("Workflow #{$workflow->id} failed on event {$event}: ".$e->getMessage());
            }
        }
    }

    /**
     * Called every minute by the scheduler (unauthenticated). Runs every active schedule-trigger
     * workflow whose cron is due since its last run. No subject: schedule triggers act entity-wide.
     */
    public function runDueScheduled(): array
    {
        $due = 0; $failed = 0;
        Workflow::withoutGlobalScope('company')
            ->where('trigger_type', 'schedule')->where('is_active', true)
            ->whereNotNull('schedule_cron')->with('actions')
            ->chunkById(200, function ($workflows) use (&$due, &$failed) {
                foreach ($workflows as $workflow) {
                    try {
                        if (!$this->isCronDue($workflow)) continue;
                        $due++;
                        $this->run($workflow, null, 'schedule');
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning("Scheduled workflow #{$workflow->id} failed: ".$e->getMessage());
                    }
                }
            });
        return ['due' => $due, 'failed' => $failed];
    }

    private function isCronDue(Workflow $workflow): bool
    {
        try {
            $cron = new CronExpression($workflow->schedule_cron);
        } catch (\Throwable) {
            return false;
        }
        $since = $workflow->last_run_at ?? $workflow->created_at ?? now()->subMinute();
        return $cron->getNextRunDate($since)->getTimestamp() <= now()->getTimestamp();
    }

    private function recordRun(Workflow $wf, ?Model $subject, string $trigger, string $status, array $log, int $ran, $started): WorkflowRun
    {
        $run = WorkflowRun::create([
            'company_id' => $wf->company_id, 'workflow_id' => $wf->id, 'trigger_type' => $trigger,
            'status' => $status,
            'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
            'log' => $log, 'actions_run' => $ran, 'started_at' => $started, 'finished_at' => now(),
            'triggered_by' => auth()->id(),
        ]);
        $wf->forceFill(['run_count' => $wf->run_count + 1, 'last_run_at' => now()])->save();
        return $run;
    }

    /** Dispatch a single action; returns a human-readable result for the log. */
    private function execute(string $type, array $config, ?Model $subject, Workflow $workflow): string
    {
        return match ($type) {
            'create_task'  => $this->doCreateTask($config, $subject, $workflow),
            'update_field' => $this->doUpdateField($config, $subject, $workflow->entity),
            'send_email'   => $this->doSendEmail($config, $subject, $workflow),
            'notify'       => 'Notification queued: '.($config['message'] ?? ''),         // structural
            'webhook'      => $this->doWebhook($config, $subject, $workflow),
            'log'          => 'Log: '.($config['message'] ?? ''),
            default        => throw new RuntimeException('Unknown action type: '.$type),
        };
    }

    private function doCreateTask(array $config, ?Model $subject, Workflow $workflow): string
    {
        $task = Task::create([
            'company_id' => $workflow->company_id,
            'title' => $config['title'] ?? 'Workflow task',
            'priority' => $config['priority'] ?? 'medium',
            'status' => 'open',
            'assigned_to' => $config['assigned_to'] ?? auth()->id(),
            'created_by' => auth()->id(),
            'due_at' => isset($config['due_in_days']) ? now()->addDays((int) $config['due_in_days']) : null,
            'related_type' => $subject ? $subject::class : null,
            'related_id' => $subject?->getKey(),
        ]);
        return 'Task #'.$task->id.' created.';
    }

    private function doUpdateField(array $config, ?Model $subject, string $entity): string
    {
        if (!$subject) throw new RuntimeException('update_field needs a subject record.');
        $field = $config['field'] ?? null;
        $allowed = self::UPDATABLE[$entity] ?? [];
        if (!in_array($field, $allowed, true)) {
            throw new RuntimeException("Field '{$field}' is not updatable on {$entity}.");
        }
        $value = $config['value'] ?? null;

        // Some columns are orchestrated, not plain data. A raw forceFill on them skips the
        // work the owning service does around the write, leaving the record internally
        // inconsistent — so those delegate. Everything else is a plain column write.
        if ($entity === 'deals' && $field === 'stage_id') {
            // DealService enforces the stage blueprint, sets pipeline_id, derives `status`
            // from the stage, stamps won_at/lost_at and writes the timeline entry. Writing
            // stage_id raw did none of it: the deal appeared in the Won column while
            // Deal::open() still counted it and won_value missed the amount entirely.
            app(DealService::class)->moveStage($subject, (int) $value);
            return "Moved to stage #{$value}.";
        }

        if ($entity === 'tickets' && $field === 'priority') {
            // Priority selects the SLA policy, so the deadlines have to be recomputed —
            // HelpdeskService::update() does that, a raw write left the old policy's clock.
            app(HelpdeskService::class)->update($subject, ['priority' => $value]);
            return "Set priority = ".json_encode($value).' (SLA recomputed).';
        }

        $subject->forceFill([$field => $value])->save();
        return "Set {$field} = ".json_encode($value);
    }

    private function doSendEmail(array $config, ?Model $subject, Workflow $workflow): string
    {
        $to = $config['to'] ?? ($subject->email ?? null);
        if (!$to) throw new RuntimeException('send_email has no recipient.');
        $email = Email::create([
            'company_id' => $workflow->company_id, 'direction' => 'outbound', 'status' => 'sent',
            'to' => [$to], 'subject' => $config['subject'] ?? 'Automated message',
            'body_html' => $config['body'] ?? '<p>Sent by a workflow.</p>',
            'template_id' => $config['template_id'] ?? null,
            'related_type' => $subject ? $subject::class : null, 'related_id' => $subject?->getKey(),
            'sent_at' => now(), 'user_id' => auth()->id(),
            'message_id' => sprintf('<%s@krama.local>', bin2hex(random_bytes(8))),
        ]);
        return 'Email #'.$email->id.' sent to '.$to.'.';
    }

    private function doWebhook(array $config, ?Model $subject, Workflow $workflow): string
    {
        $url = $config['url'] ?? null;
        if (!$url) throw new RuntimeException('webhook has no url.');
        return $this->webhooks->send($workflow, $workflow->trigger_event ?: $workflow->trigger_type, [
            'entity' => $workflow->entity,
            'subject_id' => $subject?->getKey(),
        ], $url);
    }

    private function conditionsPass(array $conditions, Model $subject): bool
    {
        foreach ($conditions as $c) {
            $actual = $subject->{$c['field'] ?? ''} ?? null;
            $value = $c['value'] ?? null;
            $ok = match ($c['op'] ?? 'eq') {
                'eq' => $actual == $value,
                'neq' => $actual != $value,
                'gt' => $actual > $value,
                'gte' => $actual >= $value,
                'lt' => $actual < $value,
                'lte' => $actual <= $value,
                'contains' => str_contains((string) $actual, (string) $value),
                default => true,
            };
            if (!$ok) return false;
        }
        return true;
    }

    private function resolveSubject(string $entity, int $id): Model
    {
        $class = self::ENTITY_MODELS[$entity] ?? null;
        if (!$class) throw new RuntimeException('Unknown entity: '.$entity);
        return $class::findOrFail($id);
    }

    public function stats(): array
    {
        return [
            'total'    => Workflow::count(),
            'active'   => Workflow::where('is_active', true)->count(),
            'runs'     => WorkflowRun::count(),
            'runs_today' => WorkflowRun::whereDate('created_at', now()->toDateString())->count(),
        ];
    }

    public function actionTypes(): array { return \App\Models\WorkflowAction::TYPES; }
}
