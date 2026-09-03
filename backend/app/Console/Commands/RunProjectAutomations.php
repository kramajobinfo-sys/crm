<?php
namespace App\Console\Commands;

use App\Models\ProjectTask;
use App\Services\ProjectOperationsService;
use Illuminate\Console\Command;

class RunProjectAutomations extends Command
{
    protected $signature='projects:run-automations {--limit=1000}';
    protected $description='Generate recurring project tasks and execute scheduled project automation rules';

    public function handle(ProjectOperationsService $operations): int
    {
        $limit=max(1,min(5000,(int)$this->option('limit'))); $recurring=0; $rules=0;
        $completed=ProjectTask::withoutGlobalScopes()->where('status','done')->whereNotNull('recurrence_frequency')->whereNull('recurrence_generated_at')->limit($limit)->get();
        foreach($completed as $task)if($operations->processRecurringTask($task))$recurring++;
        $overdue=ProjectTask::withoutGlobalScopes()->with('project')->open()->whereDate('due_date','<',today())->limit($limit)->get();
        foreach($overdue as $task)if($task->project)$rules+=$operations->processEvent('task_overdue',$task->project,$task);
        $this->info("Generated {$recurring} recurring task(s); executed {$rules} automation rule(s).");
        return self::SUCCESS;
    }
}
