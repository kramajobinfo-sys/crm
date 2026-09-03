<?php
namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAutomationRule;
use App\Models\ProjectAutomationRun;
use App\Models\ProjectTask;
use App\Models\ProjectTemplate;
use App\Models\ProjectTimeEntry;
use App\Models\TimelineActivity;
use App\Models\User;
use App\Notifications\ProjectAutomationNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectOperationsService
{
    public function templates(): array
    {
        return ProjectTemplate::with('creator:id,name')->orderByDesc('is_active')->orderBy('name')->get()->map(fn($t)=>$this->templateData($t))->all();
    }

    public function saveTemplate(Project $project, string $name, ?string $description = null): ProjectTemplate
    {
        $project->loadMissing(['milestones','tasks']);
        $base=$project->start_date?->copy() ?? today();
        $milestones=$project->milestones->map(fn($m)=>[
            'key'=>'milestone_'.$m->id,'name'=>$m->name,'description'=>$m->description,
            'due_offset_days'=>$m->due_date ? $base->diffInDays($m->due_date, false) : null,'sort_order'=>$m->sort_order,
        ])->values()->all();
        $tasks=$project->tasks->map(fn($t)=>[
            'key'=>'task_'.$t->id,'title'=>$t->title,'description'=>$t->description,'priority'=>$t->priority,
            'milestone_key'=>$t->milestone_id?'milestone_'.$t->milestone_id:null,
            'parent_key'=>$t->parent_id?'task_'.$t->parent_id:null,
            'assign_to_owner'=>(int)$t->assigned_to===(int)$project->owner_id,
            'start_offset_days'=>$t->start_date ? $base->diffInDays($t->start_date, false) : null,
            'due_offset_days'=>$t->due_date ? $base->diffInDays($t->due_date, false) : null,
            'estimated_hours'=>(float)$t->estimated_hours,'sort_order'=>$t->sort_order,
            'recurrence_frequency'=>$t->recurrence_frequency,'recurrence_interval'=>$t->recurrence_interval,
            'recurrence_end_offset_days'=>$t->recurrence_end_date ? $base->diffInDays($t->recurrence_end_date, false) : null,
        ])->values()->all();
        $duration=$project->due_date ? max(0,$base->diffInDays($project->due_date,false)) : 0;
        return ProjectTemplate::updateOrCreate(
            ['company_id'=>$project->company_id,'name'=>$name],
            ['description'=>$description ?: $project->description,'duration_days'=>$duration,'default_priority'=>$project->priority,
             'default_budget'=>$project->budget,'currency'=>$project->currency,'blueprint'=>['milestones'=>$milestones,'tasks'=>$tasks],
             'is_active'=>true,'created_by'=>auth()->id()]
        );
    }

    public function createFromTemplate(ProjectTemplate $template, array $data): Project
    {
        if (!$template->is_active) throw new RuntimeException('This project template is inactive.');
        return DB::transaction(function() use ($template,$data) {
            $start=Carbon::parse($data['start_date'] ?? today()->toDateString())->startOfDay();
            $data['start_date']=$start->toDateString();
            $data['due_date'] ??= $template->duration_days ? $start->copy()->addDays($template->duration_days)->toDateString() : null;
            $data['priority'] ??= $template->default_priority;
            $data['budget'] ??= $template->default_budget;
            $data['currency'] ??= $template->currency;
            $data['project_no'] ??= $this->nextProjectNo();
            $data['owner_id'] ??= auth()->id();
            $data['status'] ??= 'planned';
            $project=Project::create($data);
            if($project->owner_id)$project->members()->create(['company_id'=>$project->company_id,'user_id'=>$project->owner_id,'role'=>'owner','allocation_percent'=>100]);
            $milestoneIds=[];
            foreach(($template->blueprint['milestones']??[]) as $m) {
                $created=$project->milestones()->create(['company_id'=>$project->company_id,'name'=>$m['name'],'description'=>$m['description']??null,
                    'due_date'=>isset($m['due_offset_days'])?$start->copy()->addDays((int)$m['due_offset_days'])->toDateString():null,
                    'status'=>'pending','sort_order'=>$m['sort_order']??0]);
                $milestoneIds[$m['key']]=$created->id;
            }
            $taskIds=[];
            foreach(($template->blueprint['tasks']??[]) as $t) {
                $created=$project->tasks()->create(['company_id'=>$project->company_id,'milestone_id'=>$milestoneIds[$t['milestone_key']??'']??null,
                    'assigned_to'=>($t['assign_to_owner']??false)?$project->owner_id:null,'created_by'=>auth()->id(),'title'=>$t['title'],
                    'description'=>$t['description']??null,'status'=>'todo','priority'=>$t['priority']??'medium',
                    'start_date'=>isset($t['start_offset_days'])?$start->copy()->addDays((int)$t['start_offset_days'])->toDateString():null,
                    'due_date'=>isset($t['due_offset_days'])?$start->copy()->addDays((int)$t['due_offset_days'])->toDateString():null,
                    'estimated_hours'=>$t['estimated_hours']??0,'sort_order'=>$t['sort_order']??0,
                    'recurrence_frequency'=>$t['recurrence_frequency']??null,'recurrence_interval'=>$t['recurrence_interval']??1,
                    'recurrence_end_date'=>isset($t['recurrence_end_offset_days'])?$start->copy()->addDays((int)$t['recurrence_end_offset_days'])->toDateString():null]);
                $taskIds[$t['key']]=$created->id;
            }
            foreach(($template->blueprint['tasks']??[]) as $t) {
                if (!empty($t['parent_key']) && isset($taskIds[$t['key']],$taskIds[$t['parent_key']])) {
                    ProjectTask::withoutGlobalScopes()->whereKey($taskIds[$t['key']])->update(['parent_id'=>$taskIds[$t['parent_key']]]);
                }
            }
            TimelineActivity::record($project,'system','Project created from template',$template->name,['template_id'=>$template->id]);
            return $this->findProject($project->id);
        });
    }

    public function updateTemplate(ProjectTemplate $template, array $data): ProjectTemplate { $template->update($data); return $template->fresh('creator:id,name'); }
    public function deleteTemplate(ProjectTemplate $template): void { $template->delete(); }

    public function rules(): array
    {
        return ProjectAutomationRule::with(['project:id,name','creator:id,name'])->orderByDesc('is_active')->orderBy('name')->get()->map(fn($r)=>$this->ruleData($r))->all();
    }
    public function createRule(array $data): ProjectAutomationRule
    {
        $data['company_id']=auth()->user()->company_id; $data['created_by']=auth()->id();
        return ProjectAutomationRule::create($data)->load(['project:id,name','creator:id,name']);
    }
    public function updateRule(ProjectAutomationRule $rule,array $data): ProjectAutomationRule { $rule->update($data); return $rule->fresh(['project:id,name','creator:id,name']); }
    public function deleteRule(ProjectAutomationRule $rule): void { $rule->delete(); }

    public function processEvent(string $trigger, Project $project, ?ProjectTask $task=null): int
    {
        $rules=ProjectAutomationRule::withoutGlobalScopes()->where('company_id',$project->company_id)->where('trigger',$trigger)->where('is_active',true)
            ->where(fn($q)=>$q->whereNull('project_id')->orWhere('project_id',$project->id))->get();
        $count=0;
        foreach($rules as $rule) {
            $eventKey=$trigger.':'.($task?'task:'.$task->id:'project:'.$project->id);
            if($trigger==='task_overdue')$eventKey.=':'.$task?->due_date?->toDateString();
            if($this->executeRule($rule,$project,$task,$eventKey))$count++;
        }
        return $count;
    }

    public function processRecurringTask(ProjectTask $task): ?ProjectTask
    {
        return DB::transaction(function() use ($task) {
            $task=ProjectTask::withoutGlobalScopes()->lockForUpdate()->find($task->id);
            if(!$task || $task->status!=='done' || !$task->recurrence_frequency || $task->recurrence_generated_at)return null;
            $anchor=$task->due_date?->copy() ?? today();
            $next=$this->advanceDate($anchor,$task->recurrence_frequency,(int)$task->recurrence_interval);
            if($task->recurrence_end_date && $next->isAfter($task->recurrence_end_date)) {
                $task->forceFill(['recurrence_generated_at'=>now()])->save(); return null;
            }
            $start=$task->start_date ? $this->advanceDate($task->start_date->copy(),$task->recurrence_frequency,(int)$task->recurrence_interval) : null;
            $new=$task->replicate(['actual_hours','completed_at','due_reminder_sent_at','overdue_reminder_sent_at','recurrence_generated_at','deleted_at']);
            $new->status='todo'; $new->actual_hours=0; $new->completed_at=null; $new->due_reminder_sent_at=null; $new->overdue_reminder_sent_at=null;
            $new->generated_from_id=$task->id; $new->start_date=$start; $new->due_date=$next; $new->created_by=auth()->id() ?: $task->created_by; $new->save();
            $task->forceFill(['recurrence_generated_at'=>now()])->save();
            TimelineActivity::record($new,'system','Recurring task generated','Generated from '.$task->title,['generated_from_id'=>$task->id]);
            return $new;
        });
    }

    public function managementReport(Carbon $from, Carbon $to): array
    {
        $projects=Project::query()->withCount(['tasks','tasks as completed_tasks_count'=>fn($q)=>$q->where('status','done'),'tasks as overdue_tasks_count'=>fn($q)=>$q->open()->whereDate('due_date','<',today())])->get();
        $financials=ProjectTimeEntry::query()->where('status','approved')->select('project_id')
            ->selectRaw('SUM(hours) as approved_hours')->selectRaw('SUM(hours * cost_rate) as actual_cost')
            ->selectRaw('SUM(CASE WHEN billable = 1 THEN hours * bill_rate ELSE 0 END) as billable_value')->groupBy('project_id')->get()->keyBy('project_id');
        foreach($projects as $project){$money=$financials->get($project->id);$project->setAttribute('approved_hours',$money?->approved_hours??0);$project->setAttribute('actual_cost',$money?->actual_cost??0);$project->setAttribute('billable_value',$money?->billable_value??0);}
        $totalTasks=(int)$projects->sum('tasks_count'); $completedTasks=(int)$projects->sum('completed_tasks_count');
        $currency=auth()->user()->company?->base_currency ?: ($projects->first()?->currency ?: 'USD');
        $currencyProjects=$projects->where('currency',$currency);
        $actualCost=(float)$currencyProjects->sum('actual_cost'); $budget=(float)$currencyProjects->sum('budget');
        $health=['on_track'=>0,'at_risk'=>0,'overdue'=>0];
        foreach($projects as $p){
            if(!in_array($p->status,['completed','cancelled'],true)&&$p->due_date?->isBefore(today()))$health['overdue']++;
            elseif($p->overdue_tasks_count>0||($p->due_date&&$p->due_date->isBefore(today()->addDays(7))&&$p->progress<80))$health['at_risk']++;
            else $health['on_track']++;
        }
        return [
            'from'=>$from->toDateString(),'to'=>$to->toDateString(),'currency'=>$currency,
            'summary'=>['projects_total'=>$projects->count(),'active_projects'=>$projects->where('status','active')->count(),
                'completed_in_period'=>Project::where('status','completed')->whereBetween('completed_at',[$from,$to->copy()->endOfDay()])->count(),
                'overdue_projects'=>$health['overdue'],'open_tasks'=>$totalTasks-$completedTasks,'overdue_tasks'=>(int)$projects->sum('overdue_tasks_count'),
                'task_completion_rate'=>$totalTasks?round($completedTasks/$totalTasks*100,1):0,'approved_hours'=>round((float)$projects->sum('approved_hours'),2),
                'actual_cost'=>round($actualCost,2),'billable_value'=>round((float)$currencyProjects->sum('billable_value'),2),'budget_total'=>round($budget,2),'budget_variance'=>round($budget-$actualCost,2)],
            'financial_by_currency'=>$projects->groupBy('currency')->map(fn($items)=>['budget_total'=>round((float)$items->sum('budget'),2),'actual_cost'=>round((float)$items->sum('actual_cost'),2),'billable_value'=>round((float)$items->sum('billable_value'),2)]),
            'status_breakdown'=>$projects->groupBy('status')->map->count(), 'health_breakdown'=>$health,
            'projects'=>$projects->sortByDesc(fn($p)=>$p->overdue_tasks_count*1000+$p->tasks_count)->take(20)->values()->map(fn($p)=>[
                'id'=>$p->id,'project_no'=>$p->project_no,'name'=>$p->name,'status'=>$p->status,'progress'=>$p->progress,'due_date'=>$p->due_date?->toDateString(),
                'tasks'=>$p->tasks_count,'completed_tasks'=>$p->completed_tasks_count,'overdue_tasks'=>$p->overdue_tasks_count,
                'approved_hours'=>(float)($p->approved_hours??0),'actual_cost'=>(float)($p->actual_cost??0),'budget'=>(float)$p->budget,'currency'=>$p->currency,
            ])->all(),
        ];
    }

    public function templateData(ProjectTemplate $t): array { return ['id'=>$t->id,'name'=>$t->name,'description'=>$t->description,'duration_days'=>$t->duration_days,'default_priority'=>$t->default_priority,'default_budget'=>(float)$t->default_budget,'currency'=>$t->currency,'milestones_count'=>count($t->blueprint['milestones']??[]),'tasks_count'=>count($t->blueprint['tasks']??[]),'is_active'=>$t->is_active,'creator'=>$t->creator?['id'=>$t->creator->id,'name'=>$t->creator->name]:null,'created_at'=>$t->created_at?->toIso8601String()]; }
    public function ruleData(ProjectAutomationRule $r): array { return ['id'=>$r->id,'name'=>$r->name,'project_id'=>$r->project_id,'project'=>$r->project?['id'=>$r->project->id,'name'=>$r->project->name]:null,'trigger'=>$r->trigger,'conditions'=>$r->conditions,'action'=>$r->action,'action_config'=>$r->action_config,'is_active'=>$r->is_active,'last_run_at'=>$r->last_run_at?->toIso8601String(),'created_at'=>$r->created_at?->toIso8601String()]; }

    private function executeRule(ProjectAutomationRule $rule,Project $project,?ProjectTask $task,string $eventKey): bool
    {
        return DB::transaction(function()use($rule,$project,$task,$eventKey){
            if(ProjectAutomationRun::withoutGlobalScopes()->where('project_automation_rule_id',$rule->id)->where('event_key',$eventKey)->exists())return false;
            $config=$rule->action_config??[]; $details=[];
            if($rule->action==='notify_user'){
                $userId=$config['user_id']??$task?->assigned_to??$project->owner_id;
                $user=User::withoutGlobalScopes()->where('company_id',$project->company_id)->whereKey($userId)->where('is_active',true)->first();
                if($user){$user->notifyNow(new ProjectAutomationNotification($rule,$project,$task));$details['notified_user_id']=$user->id;}
            }elseif($rule->action==='create_follow_up'){
                $days=max(0,min(365,(int)($config['due_days']??3)));
                $follow=$project->tasks()->create(['company_id'=>$project->company_id,'created_by'=>auth()->id(),'assigned_to'=>$task?->assigned_to??$project->owner_id,
                    'title'=>$config['title']??'Follow up: '.($task?->title??$project->name),'status'=>'todo','priority'=>$config['priority']??'medium',
                    'due_date'=>today()->addDays($days),'estimated_hours'=>$config['estimated_hours']??0,'sort_order'=>((int)$project->tasks()->max('sort_order'))+1]);
                TimelineActivity::record($follow,'system','Task created by automation',$rule->name,['automation_rule_id'=>$rule->id]); $details['created_task_id']=$follow->id;
            }elseif($rule->action==='set_priority'&&$task){$task->forceFill(['priority'=>$config['priority']??'urgent'])->save();$details['priority']=$task->priority;}
            ProjectAutomationRun::withoutGlobalScopes()->create(['company_id'=>$project->company_id,'project_automation_rule_id'=>$rule->id,'project_id'=>$project->id,'project_task_id'=>$task?->id,'event_key'=>$eventKey,'status'=>'completed','details'=>$details]);
            $rule->forceFill(['last_run_at'=>now()])->save(); return true;
        });
    }

    private function advanceDate(Carbon $date,string $frequency,int $interval): Carbon
    {
        $interval=max(1,$interval);
        return match($frequency){'daily'=>$date->addDays($interval),'weekly'=>$date->addWeeks($interval),'monthly'=>$date->addMonthsNoOverflow($interval),default=>throw new RuntimeException('Unsupported recurrence frequency.')};
    }

    private function findProject(int $id): Project
    {
        return Project::with(['owner:id,name,email','customer:id,name,customer_no','deal:id,title,deal_no,status','members.user:id,name,email',
            'milestones.tasks'=>fn($q)=>$q->with('assignee:id,name')->orderBy('sort_order'),
            'tasks'=>fn($q)=>$q->with(['assignee:id,name','milestone:id,name'])->withCount(['dependencies','comments','attachments'])->orderBy('sort_order')])->findOrFail($id);
    }

    private function nextProjectNo(string $prefix='PRJ'): string
    {
        $last=Project::withoutGlobalScopes()->withTrashed()->where('company_id',auth()->user()->company_id)->where('project_no','like',$prefix.'-%')
            ->orderByRaw('CAST(SUBSTRING(project_no, ?) AS UNSIGNED) DESC',[strlen($prefix)+2])->value('project_no');
        return sprintf('%s-%05d',$prefix,$last?((int)substr($last,strlen($prefix)+1))+1:1);
    }
}
