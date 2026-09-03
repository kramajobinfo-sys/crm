<?php
namespace App\Services;

use App\Models\Deal;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use App\Models\TimelineActivity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProjectService
{
    public function __construct(private readonly ProjectOperationsService $operations) {}

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return Project::query()
            ->with(['owner:id,name','customer:id,name,customer_no','deal:id,title,deal_no'])
            ->withCount(['tasks','tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'done')])
            ->search($filters['q'] ?? null)
            ->when(!empty($filters['status']) && $filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['owner_id']), fn ($q) => $filters['owner_id'] === 'me' ? $q->where('owner_id', auth()->id()) : $q->where('owner_id', $filters['owner_id']))
            ->when(!empty($filters['customer_id']), fn ($q) => $q->where('customer_id', $filters['customer_id']))
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 WHEN 'on_hold' THEN 2 ELSE 3 END")
            ->orderBy('due_date')->orderByDesc('id')->paginate($perPage);
    }

    public function find(int $id): Project
    {
        return Project::with([
            'owner:id,name,email', 'customer:id,name,customer_no', 'deal:id,title,deal_no,status',
            'members.user:id,name,email',
            'milestones.tasks' => fn ($q) => $q->with('assignee:id,name')->orderBy('sort_order'),
            'tasks' => fn ($q) => $q->with(['assignee:id,name','milestone:id,name'])->withCount(['dependencies','comments','attachments'])->orderBy('sort_order'),
        ])->findOrFail($id);
    }

    public function create(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            $data['project_no'] ??= $this->nextProjectNo();
            $data['owner_id'] ??= auth()->id();
            $this->stampStatus($data);
            $project = Project::create($data);
            if ($project->owner_id) {
                $project->members()->create(['company_id'=>$project->company_id,'user_id'=>$project->owner_id,'role'=>'owner','allocation_percent'=>100]);
            }
            return $this->find($project->id);
        });
    }

    public function createFromDeal(Deal $deal): Project
    {
        if (!$deal->isWon()) throw new RuntimeException('Only a won Deal can be handed off to a Project.');
        if ($deal->project()->exists()) throw new RuntimeException('This Deal already has a Project.');
        return $this->create([
            'name'=>$deal->title, 'deal_id'=>$deal->id, 'customer_id'=>$deal->customer_id,
            'owner_id'=>$deal->owner_id, 'status'=>'planned', 'priority'=>'medium',
            'start_date'=>now()->toDateString(), 'budget'=>$deal->amount, 'currency'=>$deal->currency,
            'description'=>'Created from won Deal '.$deal->deal_no.'.',
        ]);
    }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $oldOwner = $project->owner_id;
            $oldStatus = $project->status;
            $this->stampStatus($data, $project);
            $project->update($data);
            if (array_key_exists('owner_id', $data) && $data['owner_id'] && (int) $oldOwner !== (int) $data['owner_id']) {
                $project->members()->where('user_id', $oldOwner)->where('role', 'owner')->update(['role'=>'manager']);
                $project->members()->updateOrCreate(['user_id'=>$data['owner_id']], ['company_id'=>$project->company_id,'role'=>'owner','allocation_percent'=>100]);
            }
            if ($oldStatus !== 'completed' && $project->status === 'completed') $this->operations->processEvent('project_completed',$project);
            return $this->find($project->id);
        });
    }

    public function delete(Project $project): void
    {
        DB::transaction(function () use ($project) {
            // Release the one-to-one handoff key so an accidentally deleted Project does not
            // permanently block the won Deal from being handed off again.
            if ($project->deal_id) $project->forceFill(['deal_id'=>null])->save();
            // Soft-delete delivery tasks too so they disappear from My Work with the Project.
            // Their collaboration data stays recoverable with the archived task rows.
            $project->tasks()->delete();
            $project->delete();
        });
    }

    public function addMember(Project $project, array $data): Project
    {
        $values=[
            'company_id'=>$project->company_id, 'role'=>$data['role'] ?? 'member',
            'allocation_percent'=>$data['allocation_percent'] ?? 100,
        ];
        if(array_key_exists('cost_rate',$data))$values['cost_rate']=$data['cost_rate'];
        if(array_key_exists('bill_rate',$data))$values['bill_rate']=$data['bill_rate'];
        $project->members()->updateOrCreate(['user_id'=>$data['user_id']],$values);
        return $this->find($project->id);
    }

    public function removeMember(Project $project, int $userId): Project
    {
        if ((int) $project->owner_id === $userId) throw new RuntimeException('Change the Project owner before removing this member.');
        $project->members()->where('user_id', $userId)->delete();
        return $this->find($project->id);
    }

    public function createMilestone(Project $project, array $data): Project
    {
        $data['company_id'] = $project->company_id;
        $data['sort_order'] ??= ((int) $project->milestones()->max('sort_order')) + 1;
        $this->stampCompletion($data);
        $project->milestones()->create($data);
        return $this->find($project->id);
    }

    public function updateMilestone(Project $project, int $id, array $data): Project
    {
        $milestone = $project->milestones()->findOrFail($id);
        $this->stampCompletion($data, $milestone->completed_at);
        $milestone->update($data);
        return $this->find($project->id);
    }

    public function deleteMilestone(Project $project, int $id): Project
    {
        $project->milestones()->findOrFail($id)->delete();
        return $this->find($project->id);
    }

    public function createTask(Project $project, array $data): Project
    {
        $data['company_id'] = $project->company_id;
        $data['created_by'] = auth()->id();
        $data['sort_order'] ??= ((int) $project->tasks()->max('sort_order')) + 1;
        $this->stampCompletion($data);
            $task=$project->tasks()->create($data);
            TimelineActivity::record($task,'system','Task created');
            $this->operations->processEvent('task_created',$project,$task);
            $this->recalculateProgress($project);
        return $this->find($project->id);
    }

    public function updateTask(Project $project, int $id, array $data): Project
    {
        $task = $project->tasks()->findOrFail($id);
        if (!empty($data['parent_id']) && $this->parentReaches((int)$data['parent_id'],$task->id)) {
            throw new RuntimeException('This parent would create a circular subtask chain.');
        }
        if (!empty($data['status']) && in_array($data['status'],['in_progress','review','done'],true)
            && $task->dependencies()->where('project_tasks.status','!=','done')->exists()) {
            throw new RuntimeException('Complete all prerequisite tasks before advancing this task.');
        }
        $before=$task->only(['status','assigned_to','priority','due_date']);
        if ((array_key_exists('due_date',$data) && (string)($data['due_date']??'')!==(string)($task->due_date?->toDateString()??''))
            || (array_key_exists('assigned_to',$data) && (int)($data['assigned_to']??0)!==(int)($task->assigned_to??0))
            || (array_key_exists('status',$data) && in_array($task->status,['done','cancelled'],true) && !in_array($data['status'],['done','cancelled'],true))) {
            $data['due_reminder_sent_at']=null; $data['overdue_reminder_sent_at']=null;
        }
        $this->stampCompletion($data, $task->completed_at);
        $task->update($data);
        foreach (['status','assigned_to','priority','due_date'] as $field) {
            if (array_key_exists($field,$data) && (string)($before[$field]??'') !== (string)($task->{$field}??'')) {
                TimelineActivity::record($task,$field==='status'?'status_change':'system',ucwords(str_replace('_',' ',$field)).' changed',null,['from'=>$before[$field]??null,'to'=>$task->{$field}]);
            }
        }
        if (($before['status']??null)!=='done' && $task->status==='done') {
            $this->operations->processRecurringTask($task);
            $this->operations->processEvent('task_completed',$project,$task);
        }
        $this->recalculateProgress($project);
        return $this->find($project->id);
    }

    public function deleteTask(Project $project, int $id): Project
    {
        $task=$project->tasks()->findOrFail($id);
        foreach ($task->attachments as $attachment) {
            Storage::disk($attachment->disk ?: 'public')->delete($attachment->path);
            $attachment->delete();
        }
        $task->timeline()->delete();
        $task->delete();
        $this->recalculateProgress($project);
        return $this->find($project->id);
    }

    public function showTask(Project $project, int $id): ProjectTask
    {
        return $project->tasks()->with([
            'assignee:id,name,email','creator:id,name,email','milestone:id,name','parent:id,title,status,priority,assigned_to,due_date',
            'children.assignee:id,name','dependencies.assignee:id,name','dependents.assignee:id,name',
            'comments.user:id,name,email','attachments.uploader:id,name,email',
            'timeline'=>fn($q)=>$q->with('user:id,name,email')->orderByDesc('occurred_at')->limit(100),
        ])->findOrFail($id);
    }

    public function addComment(Project $project, int $taskId, string $body): ProjectTask
    {
        $task=$project->tasks()->findOrFail($taskId);
        $task->comments()->create(['company_id'=>$project->company_id,'user_id'=>auth()->id(),'body'=>$body]);
        TimelineActivity::record($task,'note','Comment added',$body);
        return $this->showTask($project,$taskId);
    }

    public function addDependency(Project $project, int $taskId, int $dependsOnId): ProjectTask
    {
        $task=$project->tasks()->findOrFail($taskId);
        $dependency=$project->tasks()->findOrFail($dependsOnId);
        if ($task->id === $dependency->id) throw new RuntimeException('A task cannot depend on itself.');
        if ($this->dependencyReaches($dependency->id,$task->id)) throw new RuntimeException('This dependency would create a circular chain.');
        $task->dependencies()->syncWithoutDetaching([$dependency->id=>['company_id'=>$project->company_id]]);
        TimelineActivity::record($task,'system','Dependency added',$dependency->title,['depends_on_task_id'=>$dependency->id]);
        return $this->showTask($project,$taskId);
    }

    public function removeDependency(Project $project, int $taskId, int $dependsOnId): ProjectTask
    {
        $task=$project->tasks()->findOrFail($taskId);
        $task->dependencies()->detach($dependsOnId);
        TimelineActivity::record($task,'system','Dependency removed',null,['depends_on_task_id'=>$dependsOnId]);
        return $this->showTask($project,$taskId);
    }

    public function addAttachment(Project $project, int $taskId, UploadedFile $file): ProjectTask
    {
        $task=$project->tasks()->findOrFail($taskId);
        $path=$file->store('project-tasks/'.date('Y/m'),'local');
        $task->attachments()->create(['company_id'=>$project->company_id,'uploaded_by'=>auth()->id(),'disk'=>'local','path'=>$path,'name'=>$file->getClientOriginalName(),'mime'=>$file->getMimeType(),'size'=>$file->getSize()]);
        TimelineActivity::record($task,'system','File attached',$file->getClientOriginalName());
        return $this->showTask($project,$taskId);
    }

    public function removeAttachment(Project $project, int $taskId, int $attachmentId): ProjectTask
    {
        $task=$project->tasks()->findOrFail($taskId);
        $attachment=$task->attachments()->findOrFail($attachmentId);
        Storage::disk($attachment->disk ?: 'public')->delete($attachment->path);
        $name=$attachment->name; $attachment->delete();
        TimelineActivity::record($task,'system','File removed',$name);
        return $this->showTask($project,$taskId);
    }

    public function stats(): array
    {
        return [
            'active'=>Project::where('status','active')->count(),
            'planned'=>Project::where('status','planned')->count(),
            'overdue'=>Project::whereNotIn('status',['completed','cancelled'])->whereDate('due_date','<',today())->count(),
            'completed'=>Project::where('status','completed')->count(),
            'my_open_tasks'=>ProjectTask::open()->where('assigned_to',auth()->id())->count(),
        ];
    }

    public function nextProjectNo(string $prefix = 'PRJ'): string
    {
        $last = Project::withoutGlobalScopes()->withTrashed()->where('company_id',auth()->user()?->company_id)
            ->where('project_no','like',$prefix.'-%')->orderByRaw('CAST(SUBSTRING(project_no, ?) AS UNSIGNED) DESC', [strlen($prefix)+2])->value('project_no');
        return sprintf('%s-%05d', $prefix, $last ? ((int) substr($last, strlen($prefix)+1))+1 : 1);
    }

    private function recalculateProgress(Project $project): void
    {
        $total = $project->tasks()->where('status','!=','cancelled')->count();
        $done = $project->tasks()->where('status','done')->count();
        $project->forceFill(['progress'=>$total ? (int) round($done / $total * 100) : 0])->save();
    }

    private function dependencyReaches(int $fromId, int $targetId): bool
    {
        $seen=[]; $queue=[$fromId];
        while ($queue) {
            $current=array_shift($queue);
            if ($current===$targetId) return true;
            if (isset($seen[$current])) continue;
            $seen[$current]=true;
            $queue=array_merge($queue,DB::table('project_task_dependencies')->where('task_id',$current)->pluck('depends_on_task_id')->map(fn($id)=>(int)$id)->all());
        }
        return false;
    }

    private function parentReaches(int $fromId, int $targetId): bool
    {
        $seen=[]; $current=$fromId;
        while ($current) {
            if ($current===$targetId) return true;
            if (isset($seen[$current])) return true;
            $seen[$current]=true;
            $current=(int)(ProjectTask::whereKey($current)->value('parent_id') ?? 0);
        }
        return false;
    }

    private function stampStatus(array &$data, ?Project $project = null): void
    {
        if (!array_key_exists('status', $data)) return;
        $data['completed_at'] = $data['status'] === 'completed' ? ($project?->completed_at ?? now()) : null;
        if ($data['status'] === 'completed') $data['progress'] = 100;
    }

    private function stampCompletion(array &$data, mixed $existing = null): void
    {
        if (!array_key_exists('status', $data)) return;
        $data['completed_at'] = $data['status'] === 'completed' || $data['status'] === 'done' ? ($existing ?? now()) : null;
    }
}
