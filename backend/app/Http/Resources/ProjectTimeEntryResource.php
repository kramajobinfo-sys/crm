<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectTimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user=fn($u)=>$u?['id'=>$u->id,'name'=>$u->name]:null;
        return ['id'=>$this->id,'project_id'=>$this->project_id,'project_task_id'=>$this->project_task_id,'task'=>$this->whenLoaded('task',fn()=>$this->task?['id'=>$this->task->id,'title'=>$this->task->title]:null),'user'=>$this->whenLoaded('user',fn()=>$user($this->user)),'work_date'=>$this->work_date?->toDateString(),'hours'=>(float)$this->hours,'billable'=>(bool)$this->billable,'cost_rate'=>(float)$this->cost_rate,'bill_rate'=>(float)$this->bill_rate,'cost_amount'=>round((float)$this->hours*(float)$this->cost_rate,2),'billable_amount'=>$this->billable?round((float)$this->hours*(float)$this->bill_rate,2):0,'notes'=>$this->notes,'status'=>$this->status,'approver'=>$this->whenLoaded('approver',fn()=>$user($this->approver)),'approved_at'=>$this->approved_at?->toIso8601String(),'created_at'=>$this->created_at?->toIso8601String()];
    }
}
