<?php
namespace App\Http\Controllers\Api\V1\Projects;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectAutomationRule;
use App\Models\ProjectTemplate;
use App\Services\ProjectOperationsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProjectOperationsController extends Controller
{
    public function __construct(private readonly ProjectOperationsService $operations) {}

    public function templates(): JsonResponse { return $this->success($this->operations->templates()); }
    public function storeTemplate(Request $request,int $projectId): JsonResponse
    {
        $data=$request->validate(['name'=>['required','string','max:191',Rule::unique('project_templates','name')->where('company_id',$request->user()->company_id)->whereNull('deleted_at')],'description'=>'nullable|string|max:5000']);
        $template=$this->operations->saveTemplate(Project::findOrFail($projectId),$data['name'],$data['description']??null);
        return $this->success($this->operations->templateData($template->load('creator:id,name')),'Project template saved',201);
    }
    public function updateTemplate(Request $request,int $id): JsonResponse
    {
        $template=ProjectTemplate::findOrFail($id);
        $data=$request->validate(['name'=>['sometimes','string','max:191',Rule::unique('project_templates','name')->where('company_id',$request->user()->company_id)->whereNull('deleted_at')->ignore($id)],'description'=>'nullable|string|max:5000','is_active'=>'sometimes|boolean']);
        return $this->success($this->operations->templateData($this->operations->updateTemplate($template,$data)),'Project template updated');
    }
    public function destroyTemplate(int $id): JsonResponse { $this->operations->deleteTemplate(ProjectTemplate::findOrFail($id)); return $this->success(null,'Project template deleted'); }
    public function createFromTemplate(Request $request,int $id): JsonResponse
    {
        $companyId=$request->user()->company_id;
        $data=$request->validate(['name'=>'required|string|max:191','description'=>'nullable|string|max:10000','customer_id'=>['nullable','integer',Rule::exists('customers','id')->where('company_id',$companyId)->whereNull('deleted_at')],
            'owner_id'=>['nullable','integer',Rule::exists('users','id')->where('company_id',$companyId)],'start_date'=>'required|date','due_date'=>'nullable|date|after_or_equal:start_date','budget'=>'nullable|numeric|min:0|max:9999999999999','currency'=>'nullable|string|size:3','priority'=>['nullable',Rule::in(Project::PRIORITIES)]]);
        try{$project=$this->operations->createFromTemplate(ProjectTemplate::findOrFail($id),$data);}catch(RuntimeException $e){return $this->error($e->getMessage(),422);}
        return $this->success(new ProjectResource($project),'Project created from template',201);
    }

    public function rules(): JsonResponse { return $this->success($this->operations->rules()); }
    public function storeRule(Request $request): JsonResponse
    {
        $rule=$this->operations->createRule($request->validate($this->ruleRules($request)));
        return $this->success($this->operations->ruleData($rule),'Automation rule created',201);
    }
    public function updateRule(Request $request,int $id): JsonResponse
    {
        $rule=$this->operations->updateRule(ProjectAutomationRule::findOrFail($id),$request->validate($this->ruleRules($request,true)));
        return $this->success($this->operations->ruleData($rule),'Automation rule updated');
    }
    public function destroyRule(int $id): JsonResponse { $this->operations->deleteRule(ProjectAutomationRule::findOrFail($id)); return $this->success(null,'Automation rule deleted'); }

    public function report(Request $request): JsonResponse
    {
        $data=$request->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from']);
        $from=Carbon::parse($data['from']??now()->startOfYear()->toDateString())->startOfDay();
        $to=Carbon::parse($data['to']??today()->toDateString())->startOfDay();
        if($from->diffInDays($to)>366)return $this->error('Report range cannot exceed 366 days.',422);
        return $this->success($this->operations->managementReport($from,$to));
    }

    private function ruleRules(Request $request,bool $partial=false): array
    {
        $companyId=$request->user()->company_id;
        return ['name'=>[$partial?'sometimes':'required','string','max:191'],'project_id'=>['nullable','integer',Rule::exists('projects','id')->where('company_id',$companyId)->whereNull('deleted_at')],
            'trigger'=>[$partial?'sometimes':'required',Rule::in(ProjectAutomationRule::TRIGGERS)],'conditions'=>'nullable|array',
            'action'=>[$partial?'sometimes':'required',Rule::in(ProjectAutomationRule::ACTIONS)],'action_config'=>'nullable|array',
            'action_config.user_id'=>['nullable','integer',Rule::exists('users','id')->where('company_id',$companyId)],'action_config.title'=>'nullable|string|max:191',
            'action_config.due_days'=>'nullable|integer|min:0|max:365','action_config.priority'=>['nullable',Rule::in(['low','medium','high','urgent'])],
            'action_config.estimated_hours'=>'nullable|numeric|min:0|max:999999','is_active'=>'sometimes|boolean'];
    }
}
