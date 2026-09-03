<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user=fn($u)=>$u?['id'=>$u->id,'name'=>$u->name,'email'=>$u->email??null]:null;
        $task=fn($t)=>['id'=>$t->id,'title'=>$t->title,'status'=>$t->status,'priority'=>$t->priority,'assigned_to'=>$t->assigned_to,'due_date'=>$t->due_date?->toDateString()];
        return [
            'id'=>$this->id,'project_id'=>$this->project_id,'milestone_id'=>$this->milestone_id,'parent_id'=>$this->parent_id,
            'title'=>$this->title,'description'=>$this->description,'status'=>$this->status,'priority'=>$this->priority,
            'assigned_to'=>$this->assigned_to,'assignee'=>$this->whenLoaded('assignee',fn()=>$user($this->assignee)),'creator'=>$this->whenLoaded('creator',fn()=>$user($this->creator)),
            'start_date'=>$this->start_date?->toDateString(),'due_date'=>$this->due_date?->toDateString(),
            'estimated_hours'=>(float)$this->estimated_hours,'actual_hours'=>(float)$this->actual_hours,'sort_order'=>$this->sort_order,
            'recurrence_frequency'=>$this->recurrence_frequency,'recurrence_interval'=>$this->recurrence_interval,
            'recurrence_end_date'=>$this->recurrence_end_date?->toDateString(),'generated_from_id'=>$this->generated_from_id,
            'completed_at'=>$this->completed_at?->toIso8601String(),'created_at'=>$this->created_at?->toIso8601String(),'updated_at'=>$this->updated_at?->toIso8601String(),
            'milestone'=>$this->whenLoaded('milestone',fn()=>$this->milestone?['id'=>$this->milestone->id,'name'=>$this->milestone->name]:null),
            'parent'=>$this->whenLoaded('parent',fn()=>$this->parent?$task($this->parent):null),
            'children'=>$this->whenLoaded('children',fn()=>$this->children->map($task)),
            'dependencies'=>$this->whenLoaded('dependencies',fn()=>$this->dependencies->map($task)),
            'dependents'=>$this->whenLoaded('dependents',fn()=>$this->dependents->map($task)),
            'comments'=>$this->whenLoaded('comments',fn()=>$this->comments->map(fn($c)=>['id'=>$c->id,'body'=>$c->body,'user'=>$user($c->user),'created_at'=>$c->created_at?->toIso8601String(),'created_human'=>$c->created_at?->diffForHumans()])),
            'attachments'=>$this->whenLoaded('attachments',fn()=>$this->attachments->map(fn($a)=>['id'=>$a->id,'name'=>$a->name,'mime'=>$a->mime,'size'=>$a->size,'kind'=>$a->kind,'url'=>$a->url,'uploader'=>$user($a->uploader),'created_at'=>$a->created_at?->toIso8601String()])),
            'timeline'=>$this->whenLoaded('timeline',fn()=>$this->timeline->map(fn($e)=>['id'=>$e->id,'type'=>$e->type,'title'=>$e->title,'body'=>$e->body,'meta'=>$e->meta,'user'=>$user($e->user),'occurred_at'=>$e->occurred_at?->toIso8601String(),'occurred_human'=>$e->occurred_at?->diffForHumans()])),
        ];
    }
}
