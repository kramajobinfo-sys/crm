<?php
namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ProjectTask;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MyWorkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters=$request->validate(['q'=>'nullable|string|max:191','source'=>'nullable|in:all,crm,project','state'=>'nullable|in:open,completed,all']);
        $source=$filters['source'] ?? 'all'; $state=$filters['state'] ?? 'open'; $term=$filters['q'] ?? null;
        $items=collect();

        if (in_array($source,['all','crm'],true) && $request->user()->can('activities.view')) {
            $tasks=Task::with('related')->where('assigned_to',$request->user()->id)
                ->when($state==='open',fn($q)=>$q->whereIn('status',Task::OPEN_STATUSES))
                ->when($state==='completed',fn($q)=>$q->where('status','done'))
                ->when($term,fn($q)=>$q->where(fn($w)=>$w->where('title','like','%'.$term.'%')->orWhere('description','like','%'.$term.'%')))
                ->limit(200)->get()->map(fn(Task $task)=>[
                    'key'=>'crm-'.$task->id,'id'=>$task->id,'source'=>'crm','title'=>$task->title,'description'=>$task->description,
                    'status'=>$task->status,'priority'=>$task->priority,'due_at'=>$task->due_at?->toIso8601String(),
                    'context'=>$task->related ? class_basename($task->related_type).' · '.($task->related->name ?? $task->related->title ?? $task->related->deal_no ?? '#'.$task->related_id) : 'CRM Activity',
                    'project_id'=>null,'updated_at'=>$task->updated_at?->toIso8601String(),
                ]);
            $items=$items->concat($tasks);
        }

        if (in_array($source,['all','project'],true) && $request->user()->can('project_tasks.view')) {
            $tasks=ProjectTask::with('project:id,project_no,name')->where('assigned_to',$request->user()->id)
                ->when($state==='open',fn($q)=>$q->whereNotIn('status',['done','cancelled']))
                ->when($state==='completed',fn($q)=>$q->where('status','done'))
                ->when($term,fn($q)=>$q->where(fn($w)=>$w->where('title','like','%'.$term.'%')->orWhere('description','like','%'.$term.'%')))
                ->limit(200)->get()->map(fn(ProjectTask $task)=>[
                    'key'=>'project-'.$task->id,'id'=>$task->id,'source'=>'project','title'=>$task->title,'description'=>$task->description,
                    'status'=>$task->status,'priority'=>$task->priority,'due_at'=>$task->due_date?->startOfDay()->toIso8601String(),
                    'context'=>$task->project ? $task->project->project_no.' · '.$task->project->name : 'Project',
                    'project_id'=>$task->project_id,'updated_at'=>$task->updated_at?->toIso8601String(),
                ]);
            $items=$items->concat($tasks);
        }

        $items=$items->sortBy(fn($item)=>[$item['status']==='done'?1:0,$item['due_at'] ? strtotime($item['due_at']) : PHP_INT_MAX,-strtotime($item['updated_at'])])->values();
        return $this->success(['items'=>$items,'summary'=>$this->summary($items)]);
    }

    private function summary(Collection $items): array
    {
        $today=now()->toDateString();
        return [
            'open'=>$items->whereNotIn('status',['done','cancelled'])->count(),
            'overdue'=>$items->filter(fn($i)=>!in_array($i['status'],['done','cancelled'],true) && $i['due_at'] && substr($i['due_at'],0,10)<$today)->count(),
            'due_today'=>$items->filter(fn($i)=>!in_array($i['status'],['done','cancelled'],true) && $i['due_at'] && substr($i['due_at'],0,10)===$today)->count(),
            'completed'=>$items->where('status','done')->count(),
        ];
    }
}
