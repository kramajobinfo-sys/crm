<?php
namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectAutomationRule;
use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProjectAutomationNotification extends Notification
{
    use Queueable;
    public function __construct(private readonly ProjectAutomationRule $rule, private readonly Project $project, private readonly ?ProjectTask $task = null) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        $subject=$this->task?->title ?? $this->project->name;
        return [
            'message'=>$this->rule->name.': '.$subject,
            'kind'=>'project_automation',
            'project_id'=>$this->project->id,
            'project_task_id'=>$this->task?->id,
            'automation_rule_id'=>$this->rule->id,
            'url'=>'/app/projects?project='.$this->project->id.($this->task?'&task='.$this->task->id:''),
        ];
    }
}
