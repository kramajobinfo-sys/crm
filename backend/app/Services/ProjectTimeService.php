<?php
namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectTask;
use App\Models\ProjectTimeEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class ProjectTimeService
{
    public function list(Project $project,array $filters=[]): array
    {
        $entries=$project->timeEntries()->with(['user:id,name','task:id,title','approver:id,name'])
            ->when(!empty($filters['user_id']),fn($q)=>$q->where('user_id',$filters['user_id']))
            ->when(!empty($filters['status'])&&$filters['status']!=='all',fn($q)=>$q->where('status',$filters['status']))
            ->when(!empty($filters['from']),fn($q)=>$q->whereDate('work_date','>=',$filters['from']))
            ->when(!empty($filters['to']),fn($q)=>$q->whereDate('work_date','<=',$filters['to']))
            ->orderByDesc('work_date')->orderByDesc('id')->limit(500)->get();
        return ['entries'=>$entries,'summary'=>$this->summary($project)];
    }

    public function create(Project $project,array $data): ProjectTimeEntry
    {
        $userId=(int)($data['user_id']??auth()->id());
        $this->assertCanManageUser($userId);
        if (!empty($data['project_task_id'])) $project->tasks()->findOrFail($data['project_task_id']);
        $member=$project->members()->where('user_id',$userId)->first();
        $data['company_id']=$project->company_id; $data['user_id']=$userId;
        $data['cost_rate']=$member?->cost_rate??0; $data['bill_rate']=$member?->bill_rate??0;
        $data['status']=$data['status']??'draft';
        return $project->timeEntries()->create($data)->load(['user:id,name','task:id,title','approver:id,name']);
    }

    public function update(Project $project,int $id,array $data): ProjectTimeEntry
    {
        $entry=$project->timeEntries()->findOrFail($id);
        $this->assertEditable($entry);
        if (!empty($data['project_task_id'])) $project->tasks()->findOrFail($data['project_task_id']);
        $entry->update($data);
        return $entry->load(['user:id,name','task:id,title','approver:id,name']);
    }

    public function delete(Project $project,int $id): void
    {
        $entry=$project->timeEntries()->findOrFail($id); $this->assertEditable($entry); $entry->delete();
    }

    public function decide(Project $project,int $id,string $decision): ProjectTimeEntry
    {
        $entry=$project->timeEntries()->findOrFail($id);
        if ($entry->status!=='submitted') throw new RuntimeException('Only submitted time can be approved or rejected.');
        $entry->update(['status'=>$decision,'approved_by'=>auth()->id(),'approved_at'=>now()]);
        if($entry->project_task_id){
            $actual=(float)ProjectTimeEntry::where('project_task_id',$entry->project_task_id)->where('status','approved')->sum('hours');
            ProjectTask::whereKey($entry->project_task_id)->update(['actual_hours'=>$actual]);
        }
        return $entry->load(['user:id,name','task:id,title','approver:id,name']);
    }

    public function summary(Project $project): array
    {
        $approved=$project->timeEntries()->where('status','approved')->get(['hours','billable','cost_rate','bill_rate']);
        return [
            'approved_hours'=>(float)$approved->sum('hours'),
            'pending_hours'=>(float)$project->timeEntries()->where('status','submitted')->sum('hours'),
            'actual_cost'=>round($approved->sum(fn($e)=>(float)$e->hours*(float)$e->cost_rate),2),
            'billable_value'=>round($approved->sum(fn($e)=>$e->billable?(float)$e->hours*(float)$e->bill_rate:0),2),
            'budget'=>(float)$project->budget,
        ];
    }

    public function workload(Carbon $from,Carbon $to): array
    {
        $days=$this->businessDays($from,$to);
        $members=ProjectMember::with(['user:id,name,email','project:id,name,project_no,status'])
            ->whereHas('project',fn($q)=>$q->whereIn('status',['planned','active','on_hold']))->get();
        $taskHours=ProjectTask::open()->whereNotNull('assigned_to')
            ->where(fn($q)=>$q->whereNull('due_date')->orWhereBetween('due_date',[$from->toDateString(),$to->toDateString()]))
            ->get()->groupBy('assigned_to')->map(fn(Collection $tasks)=>(float)$tasks->sum(fn($t)=>(float)$t->estimated_hours));
        return $members->groupBy('user_id')->map(function(Collection $rows,$userId) use($days,$taskHours){
            $allocation=(int)$rows->sum('allocation_percent'); $capacity=$days*8;
            $planned=(float)($taskHours[$userId]??0);
            return ['user'=>['id'=>(int)$userId,'name'=>$rows->first()->user?->name,'email'=>$rows->first()->user?->email],'allocation_percent'=>$allocation,'capacity_hours'=>round($capacity,1),'planned_hours'=>round($planned,1),'utilization_percent'=>$capacity>0?round($planned/$capacity*100,1):0,'projects'=>$rows->map(fn($m)=>['id'=>$m->project_id,'name'=>$m->project?->name,'project_no'=>$m->project?->project_no,'allocation_percent'=>$m->allocation_percent])->values()];
        })->values()->sortByDesc('utilization_percent')->values()->all();
    }

    private function assertCanManageUser(int $userId): void
    {
        if ($userId!==auth()->id()&&!auth()->user()->can('timesheets.approve')) throw new RuntimeException('You may only log time for yourself.');
    }
    private function assertEditable(ProjectTimeEntry $entry): void
    {
        $this->assertCanManageUser((int)$entry->user_id);
        if ($entry->status==='approved') throw new RuntimeException('Approved time entries cannot be changed.');
    }
    private function businessDays(Carbon $from,Carbon $to): int
    {
        $days=0; for($d=$from->copy();$d->lte($to);$d->addDay()) if(!$d->isWeekend()) $days++; return $days;
    }
}
