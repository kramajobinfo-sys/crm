<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = fn ($u) => $u ? ['id'=>$u->id,'name'=>$u->name,'email'=>$u->email ?? null] : null;
        return [
            'id'=>$this->id, 'project_no'=>$this->project_no, 'name'=>$this->name, 'description'=>$this->description,
            'status'=>$this->status, 'priority'=>$this->priority, 'progress'=>$this->progress,
            'start_date'=>$this->start_date?->toDateString(), 'due_date'=>$this->due_date?->toDateString(),
            'completed_at'=>$this->completed_at?->toIso8601String(), 'budget'=>(float)$this->budget, 'currency'=>$this->currency,
            'owner'=>$this->whenLoaded('owner', fn () => $user($this->owner)),
            'customer'=>$this->whenLoaded('customer', fn () => $this->customer ? ['id'=>$this->customer->id,'name'=>$this->customer->name,'customer_no'=>$this->customer->customer_no] : null),
            'deal'=>$this->whenLoaded('deal', fn () => $this->deal ? ['id'=>$this->deal->id,'title'=>$this->deal->title,'deal_no'=>$this->deal->deal_no,'status'=>$this->deal->status] : null),
            'tasks_count'=>$this->whenCounted('tasks'), 'completed_tasks_count'=>$this->when(isset($this->completed_tasks_count), $this->completed_tasks_count),
            'members'=>$this->whenLoaded('members', fn () => $this->members->map(fn ($m) => ['id'=>$m->id,'user'=>$user($m->user),'role'=>$m->role,'allocation_percent'=>$m->allocation_percent,'cost_rate'=>(float)$m->cost_rate,'bill_rate'=>(float)$m->bill_rate])),
            'milestones'=>$this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($m) => ['id'=>$m->id,'name'=>$m->name,'description'=>$m->description,'status'=>$m->status,'due_date'=>$m->due_date?->toDateString(),'completed_at'=>$m->completed_at?->toIso8601String(),'sort_order'=>$m->sort_order])),
            'tasks'=>$this->whenLoaded('tasks', fn () => $this->tasks->map(fn ($t) => ['id'=>$t->id,'title'=>$t->title,'description'=>$t->description,'status'=>$t->status,'priority'=>$t->priority,'milestone_id'=>$t->milestone_id,'milestone'=>$t->milestone ? ['id'=>$t->milestone->id,'name'=>$t->milestone->name] : null,'parent_id'=>$t->parent_id,'assigned_to'=>$t->assigned_to,'assignee'=>$user($t->assignee),'start_date'=>$t->start_date?->toDateString(),'due_date'=>$t->due_date?->toDateString(),'estimated_hours'=>(float)$t->estimated_hours,'actual_hours'=>(float)$t->actual_hours,'sort_order'=>$t->sort_order,'completed_at'=>$t->completed_at?->toIso8601String(),'recurrence_frequency'=>$t->recurrence_frequency,'recurrence_interval'=>$t->recurrence_interval,'recurrence_end_date'=>$t->recurrence_end_date?->toDateString(),'generated_from_id'=>$t->generated_from_id,'dependencies_count'=>$t->dependencies_count??0,'comments_count'=>$t->comments_count??0,'attachments_count'=>$t->attachments_count??0])),
            'created_at'=>$this->created_at?->toIso8601String(), 'updated_at'=>$this->updated_at?->toIso8601String(),
        ];
    }
}
