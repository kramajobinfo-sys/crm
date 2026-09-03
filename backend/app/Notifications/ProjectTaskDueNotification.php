<?php
namespace App\Notifications;

use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectTaskDueNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly ProjectTask $task,private readonly string $kind) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        $prefix=$this->kind==='overdue'?'Overdue project task':'Project task due tomorrow';
        return ['message'=>$prefix.': '.$this->task->title,'kind'=>$this->kind,'project_id'=>$this->task->project_id,'project_task_id'=>$this->task->id,'due_date'=>$this->task->due_date?->toDateString(),'url'=>'/app/projects?project='.$this->task->project_id.'&task='.$this->task->id];
    }
}
