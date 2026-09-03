<?php
namespace App\Console\Commands;

use App\Services\WorkflowService;
use Illuminate\Console\Command;

class WorkflowsRunScheduled extends Command
{
    protected $signature = 'workflows:run-scheduled';
    protected $description = 'Run active schedule-trigger workflows whose cron is due';

    public function handle(WorkflowService $workflows): int
    {
        $result = $workflows->runDueScheduled();
        $this->info("Ran {$result['due']} due workflow(s); {$result['failed']} failed.");
        return self::SUCCESS;
    }
}
