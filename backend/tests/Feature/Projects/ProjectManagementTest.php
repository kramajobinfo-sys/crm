<?php

namespace Tests\Feature\Projects;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Project;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_project_creation_adds_owner_and_task_progress_is_recalculated(): void
    {
        [$company,$user] = $this->tenant('Tenant A','TEN-A');
        $this->actingAs($user,'api');

        $projectId = $this->postJson('/api/v1/projects',['name'=>'ERP rollout','owner_id'=>$user->id,'status'=>'active'])
            ->assertCreated()->assertJsonPath('data.members.0.role','owner')->json('data.id');

        $this->postJson("/api/v1/projects/{$projectId}/tasks",['title'=>'Discovery'])->assertCreated()->assertJsonPath('data.progress',0);
        $taskId = Project::findOrFail($projectId)->tasks()->firstOrFail()->id;
        $this->putJson("/api/v1/projects/{$projectId}/tasks/{$taskId}",['status'=>'done'])
            ->assertOk()->assertJsonPath('data.progress',100)->assertJsonPath('data.tasks.0.status','done');
    }

    public function test_won_deal_handoff_is_single_and_preserves_commercial_context(): void
    {
        [$company,$user,$stage] = $this->tenant('Tenant A','TEN-A',true);
        $account = Customer::withoutGlobalScopes()->create(['company_id'=>$company->id,'customer_no'=>'ACC-1','name'=>'Acme','type'=>'company','status'=>'active']);
        $deal = Deal::withoutGlobalScopes()->create(['company_id'=>$company->id,'deal_no'=>'DEAL-1','title'=>'Acme rollout','pipeline_id'=>$stage->pipeline_id,'stage_id'=>$stage->id,'customer_id'=>$account->id,'owner_id'=>$user->id,'amount'=>25000,'currency'=>'USD','probability'=>100,'status'=>'won','won_at'=>now()]);
        $this->actingAs($user,'api');

        $this->postJson("/api/v1/projects/from-deal/{$deal->id}")->assertCreated()
            ->assertJsonPath('data.customer.id',$account->id)->assertJsonPath('data.deal.id',$deal->id)->assertJsonPath('data.budget',25000);
        $this->postJson("/api/v1/projects/from-deal/{$deal->id}")->assertUnprocessable()->assertJsonPath('message','This Deal already has a Project.');
    }

    public function test_foreign_tenant_relations_and_children_are_rejected(): void
    {
        [$companyA,$userA] = $this->tenant('Tenant A','TEN-A');
        [$companyB,$userB] = $this->tenant('Tenant B','TEN-B');
        $foreignAccount = Customer::withoutGlobalScopes()->create(['company_id'=>$companyB->id,'customer_no'=>'ACC-B','name'=>'Foreign','type'=>'company','status'=>'active']);
        $this->actingAs($userA,'api');

        $this->postJson('/api/v1/projects',['name'=>'Invalid','customer_id'=>$foreignAccount->id,'owner_id'=>$userB->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['customer_id','owner_id']);

        $project = Project::withoutGlobalScopes()->create(['company_id'=>$companyB->id,'project_no'=>'PRJ-B','name'=>'Foreign project','owner_id'=>$userB->id]);
        $this->getJson("/api/v1/projects/{$project->id}")->assertNotFound();
    }

    public function test_task_collaboration_dependencies_and_files_are_tenant_safe(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$company,$user] = $this->tenant('Tenant A','TEN-A');
        $this->actingAs($user,'api');
        $projectId=$this->postJson('/api/v1/projects',['name'=>'Delivery'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/projects/{$projectId}/tasks",['title'=>'Foundation'])->assertCreated();
        $this->postJson("/api/v1/projects/{$projectId}/tasks",['title'=>'Launch'])->assertCreated();
        $project=Project::findOrFail($projectId); $first=$project->tasks()->where('title','Foundation')->firstOrFail(); $second=$project->tasks()->where('title','Launch')->firstOrFail();

        $this->postJson("/api/v1/projects/{$projectId}/tasks/{$second->id}/comments",['body'=>'Customer approved the schedule.'])
            ->assertCreated()->assertJsonPath('data.comments.0.body','Customer approved the schedule.');
        $this->postJson("/api/v1/projects/{$projectId}/tasks/{$second->id}/dependencies",['depends_on_task_id'=>$first->id])
            ->assertCreated()->assertJsonPath('data.dependencies.0.id',$first->id);
        $this->postJson("/api/v1/projects/{$projectId}/tasks/{$first->id}/dependencies",['depends_on_task_id'=>$second->id])
            ->assertUnprocessable()->assertJsonPath('message','This dependency would create a circular chain.');
        $this->putJson("/api/v1/projects/{$projectId}/tasks/{$second->id}",['status'=>'done'])
            ->assertUnprocessable()->assertJsonPath('message','Complete all prerequisite tasks before advancing this task.');

        $this->post("/api/v1/projects/{$projectId}/tasks/{$second->id}/attachments",['file'=>UploadedFile::fake()->create('scope.pdf',80,'application/pdf')],['Accept'=>'application/json'])
            ->assertCreated()->assertJsonPath('data.attachments.0.name','scope.pdf');
        $attachment=Attachment::firstOrFail(); Storage::disk('local')->assertExists($attachment->path);
        $this->deleteJson("/api/v1/projects/{$projectId}/tasks/{$second->id}/attachments/{$attachment->id}")->assertOk();
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_subtask_hierarchy_rejects_cycles(): void
    {
        [$company,$user]=$this->tenant('Tenant A','TEN-A'); $this->actingAs($user,'api');
        $projectId=$this->postJson('/api/v1/projects',['name'=>'Hierarchy'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/projects/{$projectId}/tasks",['title'=>'Parent'])->assertCreated();
        $parent=Project::findOrFail($projectId)->tasks()->firstOrFail();
        $this->postJson("/api/v1/projects/{$projectId}/tasks",['title'=>'Child','parent_id'=>$parent->id])->assertCreated();
        $child=Project::findOrFail($projectId)->tasks()->where('title','Child')->firstOrFail();
        $this->putJson("/api/v1/projects/{$projectId}/tasks/{$parent->id}",['parent_id'=>$child->id])
            ->assertUnprocessable()->assertJsonPath('message','This parent would create a circular subtask chain.');
    }

    /** @return array{Company,User,PipelineStage|null} */
    private function tenant(string $name,string $code,bool $won=false): array
    {
        $company=Company::create(['name'=>$name,'code'=>$code,'is_active'=>true]);
        $user=User::create(['company_id'=>$company->id,'name'=>$name.' User','email'=>strtolower($code).'@test.local','password'=>'password','is_active'=>true]);
        $pipeline=Pipeline::withoutGlobalScopes()->create(['company_id'=>$company->id,'name'=>'Sales','code'=>'SALES','is_default'=>true,'is_active'=>true]);
        $stage=PipelineStage::withoutGlobalScopes()->create(['company_id'=>$company->id,'pipeline_id'=>$pipeline->id,'name'=>$won?'Won':'Qualification','code'=>$won?'WON':'QUAL','order_index'=>1,'probability'=>$won?100:20,'is_won'=>$won]);
        return [$company,$user,$stage];
    }
}
