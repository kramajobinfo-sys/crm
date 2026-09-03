<?php
namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectTaskResource;
use App\Http\Resources\ProjectTimeEntryResource;
use App\Models\Deal;
use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectService;
use App\Services\ProjectTimeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $projects,private readonly ProjectTimeService $time) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['q'=>'nullable|string|max:191','status'=>['nullable',Rule::in(array_merge(['all'],Project::STATUSES))],'owner_id'=>'nullable|string','customer_id'=>'nullable|integer','per_page'=>'nullable|integer|min:1|max:100']);
        return $this->paginated($this->projects->paginate($filters, (int)($filters['per_page'] ?? 25)), ProjectResource::class);
    }

    public function stats(): JsonResponse { return $this->success($this->projects->stats()); }
    public function meta(): JsonResponse { return $this->success(['statuses'=>Project::STATUSES,'priorities'=>Project::PRIORITIES,'task_statuses'=>ProjectTask::STATUSES,'milestone_statuses'=>ProjectMilestone::STATUSES,'member_roles'=>ProjectMember::ROLES,'next_project_no'=>$this->projects->nextProjectNo(),'users'=>User::where('is_active',true)->orderBy('name')->limit(250)->get(['id','name','email']),'customers'=>Customer::orderBy('name')->limit(250)->get(['id','name','customer_no'])]); }
    public function show(int $id): JsonResponse { return $this->success(new ProjectResource($this->projects->find($id))); }
    public function store(Request $request): JsonResponse { return $this->success(new ProjectResource($this->projects->create($request->validate($this->projectRules($request)))), 'Project created', 201); }

    public function fromDeal(int $dealId): JsonResponse
    {
        try { $project = $this->projects->createFromDeal(Deal::findOrFail($dealId)); }
        catch (RuntimeException $e) { return $this->error($e->getMessage(), 422); }
        return $this->success(new ProjectResource($project), 'Project created from Deal', 201);
    }

    public function update(Request $request, int $id): JsonResponse { return $this->success(new ProjectResource($this->projects->update(Project::findOrFail($id), $request->validate($this->projectRules($request, $id, true)))), 'Project updated'); }
    public function destroy(int $id): JsonResponse { $this->projects->delete(Project::findOrFail($id)); return $this->success(null, 'Project deleted'); }

    public function addMember(Request $request, int $id): JsonResponse
    {
        $project=Project::findOrFail($id); $companyId=$request->user()->company_id;
        $data=$request->validate(['user_id'=>['required','integer',Rule::exists('users','id')->where('company_id',$companyId)],'role'=>['required',Rule::in(ProjectMember::ROLES)],'allocation_percent'=>'nullable|integer|min:1|max:100','cost_rate'=>'nullable|numeric|min:0|max:999999999','bill_rate'=>'nullable|numeric|min:0|max:999999999']);
        return $this->success(new ProjectResource($this->projects->addMember($project,$data)), 'Project member saved');
    }
    public function removeMember(int $id, int $userId): JsonResponse
    {
        try { $project=$this->projects->removeMember(Project::findOrFail($id),$userId); }
        catch (RuntimeException $e) { return $this->error($e->getMessage(),422); }
        return $this->success(new ProjectResource($project),'Project member removed');
    }

    public function storeMilestone(Request $request, int $id): JsonResponse { return $this->success(new ProjectResource($this->projects->createMilestone(Project::findOrFail($id),$request->validate($this->milestoneRules()))),'Milestone created',201); }
    public function updateMilestone(Request $request, int $id, int $milestoneId): JsonResponse { return $this->success(new ProjectResource($this->projects->updateMilestone(Project::findOrFail($id),$milestoneId,$request->validate($this->milestoneRules(true)))),'Milestone updated'); }
    public function destroyMilestone(int $id, int $milestoneId): JsonResponse { return $this->success(new ProjectResource($this->projects->deleteMilestone(Project::findOrFail($id),$milestoneId)),'Milestone deleted'); }

    public function storeTask(Request $request, int $id): JsonResponse
    {
        $project=Project::findOrFail($id); return $this->success(new ProjectResource($this->projects->createTask($project,$request->validate($this->taskRules($request,$project)))),'Project task created',201);
    }
    public function showTask(int $id, int $taskId): JsonResponse
    {
        return $this->success(new ProjectTaskResource($this->projects->showTask(Project::findOrFail($id),$taskId)));
    }
    public function updateTask(Request $request, int $id, int $taskId): JsonResponse
    {
        $project=Project::findOrFail($id); $data=$request->validate($this->taskRules($request,$project,true,$taskId));
        try { $updated=$this->projects->updateTask($project,$taskId,$data); }
        catch (RuntimeException $e) { return $this->error($e->getMessage(),422); }
        return $this->success(new ProjectResource($updated),'Project task updated');
    }
    public function destroyTask(int $id, int $taskId): JsonResponse { return $this->success(new ProjectResource($this->projects->deleteTask(Project::findOrFail($id),$taskId)),'Project task deleted'); }

    public function storeComment(Request $request, int $id, int $taskId): JsonResponse
    {
        $data=$request->validate(['body'=>'required|string|max:5000']);
        return $this->success(new ProjectTaskResource($this->projects->addComment(Project::findOrFail($id),$taskId,$data['body'])),'Comment added',201);
    }

    public function storeDependency(Request $request, int $id, int $taskId): JsonResponse
    {
        $project=Project::findOrFail($id);
        $data=$request->validate(['depends_on_task_id'=>['required','integer',Rule::exists('project_tasks','id')->where('company_id',$request->user()->company_id)->where('project_id',$project->id)->whereNull('deleted_at')]]);
        try { $task=$this->projects->addDependency($project,$taskId,(int)$data['depends_on_task_id']); }
        catch (RuntimeException $e) { return $this->error($e->getMessage(),422); }
        return $this->success(new ProjectTaskResource($task),'Dependency added',201);
    }

    public function destroyDependency(int $id, int $taskId, int $dependsOnId): JsonResponse
    {
        return $this->success(new ProjectTaskResource($this->projects->removeDependency(Project::findOrFail($id),$taskId,$dependsOnId)),'Dependency removed');
    }

    public function storeTaskAttachment(Request $request, int $id, int $taskId): JsonResponse
    {
        $request->validate(['file'=>'required|file|max:15360|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        return $this->success(new ProjectTaskResource($this->projects->addAttachment(Project::findOrFail($id),$taskId,$request->file('file'))),'File attached',201);
    }

    public function destroyTaskAttachment(int $id, int $taskId, int $attachmentId): JsonResponse
    {
        return $this->success(new ProjectTaskResource($this->projects->removeAttachment(Project::findOrFail($id),$taskId,$attachmentId)),'File removed');
    }

    public function timeEntries(Request $request,int $id): JsonResponse
    {
        $filters=$request->validate(['user_id'=>'nullable|integer','status'=>['nullable',Rule::in(array_merge(['all'],\App\Models\ProjectTimeEntry::STATUSES))],'from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $result=$this->time->list(Project::findOrFail($id),$filters);
        return $this->success(['entries'=>ProjectTimeEntryResource::collection($result['entries']),'summary'=>$result['summary']]);
    }
    public function storeTimeEntry(Request $request,int $id): JsonResponse
    {
        $project=Project::findOrFail($id); $data=$request->validate($this->timeRules($request,$project));
        try{$entry=$this->time->create($project,$data);}catch(RuntimeException $e){return $this->error($e->getMessage(),422);}
        return $this->success(new ProjectTimeEntryResource($entry),'Time entry created',201);
    }
    public function updateTimeEntry(Request $request,int $id,int $entryId): JsonResponse
    {
        $project=Project::findOrFail($id); $data=$request->validate($this->timeRules($request,$project,true));
        try{$entry=$this->time->update($project,$entryId,$data);}catch(RuntimeException $e){return $this->error($e->getMessage(),422);}
        return $this->success(new ProjectTimeEntryResource($entry),'Time entry updated');
    }
    public function destroyTimeEntry(int $id,int $entryId): JsonResponse
    {
        try{$this->time->delete(Project::findOrFail($id),$entryId);}catch(RuntimeException $e){return $this->error($e->getMessage(),422);}
        return $this->success(null,'Time entry deleted');
    }
    public function decideTimeEntry(Request $request,int $id,int $entryId): JsonResponse
    {
        $data=$request->validate(['decision'=>'required|in:approved,rejected']);
        try{$entry=$this->time->decide(Project::findOrFail($id),$entryId,$data['decision']);}catch(RuntimeException $e){return $this->error($e->getMessage(),422);}
        return $this->success(new ProjectTimeEntryResource($entry),'Time entry '.$data['decision']);
    }
    public function workload(Request $request): JsonResponse
    {
        $data=$request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $from=Carbon::parse($data['from']??now()->toDateString())->startOfDay(); $to=Carbon::parse($data['to']??now()->addDays(30)->toDateString())->startOfDay();
        if($from->diffInDays($to)>92)return $this->error('Workload range cannot exceed 92 days.',422);
        return $this->success(['from'=>$from->toDateString(),'to'=>$to->toDateString(),'items'=>$this->time->workload($from,$to)]);
    }

    private function projectRules(Request $request, ?int $id=null, bool $partial=false): array
    {
        $companyId=$request->user()->company_id;
        return ['project_no'=>['nullable','string','max:32',Rule::unique('projects','project_no')->where('company_id',$companyId)->ignore($id)],'name'=>[$partial?'sometimes':'required','string','max:191'],'description'=>'nullable|string|max:10000','status'=>[$partial?'sometimes':'nullable',Rule::in(Project::STATUSES)],'priority'=>[$partial?'sometimes':'nullable',Rule::in(Project::PRIORITIES)],'customer_id'=>['nullable','integer',Rule::exists('customers','id')->where('company_id',$companyId)->whereNull('deleted_at')],'owner_id'=>['nullable','integer',Rule::exists('users','id')->where('company_id',$companyId)],'start_date'=>'nullable|date','due_date'=>'nullable|date|after_or_equal:start_date','budget'=>'nullable|numeric|min:0|max:9999999999999','currency'=>'nullable|string|size:3'];
    }
    private function milestoneRules(bool $partial=false): array
    {
        return ['name'=>[$partial?'sometimes':'required','string','max:191'],'description'=>'nullable|string|max:5000','due_date'=>'nullable|date','status'=>[$partial?'sometimes':'nullable',Rule::in(ProjectMilestone::STATUSES)],'sort_order'=>'nullable|integer|min:0'];
    }
    private function taskRules(Request $request, Project $project, bool $partial=false, ?int $taskId=null): array
    {
        $companyId=$request->user()->company_id;
        return ['title'=>[$partial?'sometimes':'required','string','max:191'],'description'=>'nullable|string|max:10000','status'=>[$partial?'sometimes':'nullable',Rule::in(ProjectTask::STATUSES)],'priority'=>[$partial?'sometimes':'nullable',Rule::in(ProjectTask::PRIORITIES)],'milestone_id'=>['nullable','integer',Rule::exists('project_milestones','id')->where('project_id',$project->id)->where('company_id',$companyId)],'parent_id'=>['nullable','integer',Rule::exists('project_tasks','id')->where('project_id',$project->id)->where('company_id',$companyId)->when($taskId,fn($rule)=>$rule->where('id','!=',$taskId))],'assigned_to'=>['nullable','integer',Rule::exists('users','id')->where('company_id',$companyId)],'start_date'=>'nullable|date','due_date'=>'nullable|date|after_or_equal:start_date','estimated_hours'=>'nullable|numeric|min:0|max:999999','actual_hours'=>'nullable|numeric|min:0|max:999999','sort_order'=>'nullable|integer|min:0','recurrence_frequency'=>['nullable',Rule::in(['daily','weekly','monthly'])],'recurrence_interval'=>'nullable|integer|min:1|max:52','recurrence_end_date'=>'nullable|date|after_or_equal:due_date'];
    }
    private function timeRules(Request $request,Project $project,bool $partial=false): array
    {
        $companyId=$request->user()->company_id;
        return ['project_task_id'=>['nullable','integer',Rule::exists('project_tasks','id')->where('company_id',$companyId)->where('project_id',$project->id)->whereNull('deleted_at')],'user_id'=>['nullable','integer',Rule::exists('users','id')->where('company_id',$companyId)],'work_date'=>[$partial?'sometimes':'required','date'],'hours'=>[$partial?'sometimes':'required','numeric','gt:0','max:24'],'billable'=>'nullable|boolean','notes'=>'nullable|string|max:2000','status'=>[$partial?'sometimes':'nullable',Rule::in(['draft','submitted'])]];
    }
}
