<?php
namespace App\Jobs;

use App\Services\WorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs an event-triggered workflow off the request path. Dispatched after commit by
 * WorkflowService::fireEvent so automation (webhook POSTs, SMTP sends) never runs inside the
 * triggering request's DB transaction. The subject is re-resolved unscoped since the worker
 * is unauthenticated.
 */
class RunWorkflowEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $entity,
        public string $event,
        public string $subjectType,
        public int|string $subjectId,
    ) {}

    public function handle(WorkflowService $workflows): void
    {
        if (!class_exists($this->subjectType)) return;
        $subject = $this->subjectType::withoutGlobalScopes()->find($this->subjectId);
        if ($subject) {
            $workflows->runEventNow($this->entity, $this->event, $subject);
        }
    }
}
