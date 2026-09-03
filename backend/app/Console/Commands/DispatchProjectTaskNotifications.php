<?php
namespace App\Console\Commands;

use App\Models\ProjectTask;
use App\Notifications\ProjectTaskDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchProjectTaskNotifications extends Command
{
    protected $signature='projects:dispatch-task-notifications {--limit=500}';
    protected $description='Send due-soon and overdue notifications for assigned project tasks';

    public function handle(): int
    {
        $limit=max(1,min(2000,(int)$this->option('limit'))); $processed=0;
        $ids=ProjectTask::withoutGlobalScopes()->whereNotNull('assigned_to')->whereNotIn('status',['done','cancelled'])
            ->where(function($q){$q->where(fn($w)=>$w->whereDate('due_date',today()->addDay())->whereNull('due_reminder_sent_at'))->orWhere(fn($w)=>$w->whereDate('due_date','<',today())->whereNull('overdue_reminder_sent_at'));})
            ->orderBy('due_date')->limit($limit)->pluck('id');
        foreach($ids as $id){
            $sent=DB::transaction(function()use($id){
                $task=ProjectTask::withoutGlobalScopes()->with('assignee')->lockForUpdate()->find($id);
                if(!$task||in_array($task->status,['done','cancelled'],true)||!$task->due_date)return false;
                $kind=null;
                if($task->due_date->isBefore(today())&&!$task->overdue_reminder_sent_at)$kind='overdue';
                elseif($task->due_date->isSameDay(today()->addDay())&&!$task->due_reminder_sent_at)$kind='due_soon';
                if(!$kind)return false;
                if($task->assignee?->is_active)$task->assignee->notifyNow(new ProjectTaskDueNotification($task,$kind));
                $column=$kind==='overdue'?'overdue_reminder_sent_at':'due_reminder_sent_at';
                $task->forceFill([$column=>now()])->save();
                return true;
            });
            if($sent)$processed++;
        }
        $this->info("Dispatched {$processed} project task notification(s)."); return self::SUCCESS;
    }
}
