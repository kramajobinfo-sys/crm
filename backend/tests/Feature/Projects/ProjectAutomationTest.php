<?php

namespace Tests\Feature\Projects;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectAutomationRule;
use App\Models\ProjectMember;
use App\Models\ProjectTask;
use App\Models\ProjectTimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAutomationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutMiddleware(); }

    public function test_project_can_be_saved_and_recreated_from_template(): void
    {
        [$company,$user,$project]=$this->context(); $this->actingAs($user,'api');
        $milestone=$project->milestones()->create(['company_id'=>$company->id,'name'=>'Go live','due_date'=>today()->addDays(10),'sort_order'=>1]);
        $project->tasks()->create(['company_id'=>$company->id,'milestone_id'=>$milestone->id,'assigned_to'=>$user->id,'created_by'=>$user->id,'title'=>'Configure','status'=>'todo','due_date'=>today()->addDays(5),'estimated_hours'=>8]);
        $templateId=$this->postJson("/api/v1/projects/templates/from-project/{$project->id}",['name'=>'Standard rollout'])->assertCreated()
            ->assertJsonPath('data.tasks_count',1)->assertJsonPath('data.milestones_count',1)->json('data.id');
        $newId=$this->postJson("/api/v1/projects/templates/{$templateId}/create-project",['name'=>'Customer B rollout','start_date'=>today()->addMonth()->toDateString(),'owner_id'=>$user->id])
            ->assertCreated()->assertJsonPath('data.name','Customer B rollout')->json('data.id');
        $copy=Project::withoutGlobalScopes()->with(['milestones','tasks'])->findOrFail($newId);
        $this->assertCount(1,$copy->milestones); $this->assertCount(1,$copy->tasks);
        $this->assertSame(today()->addMonth()->addDays(5)->toDateString(),$copy->tasks->first()->due_date->toDateString());
    }

    public function test_completing_recurring_task_generates_the_next_occurrence_once(): void
    {
        [$company,$user,$project]=$this->context(); $this->actingAs($user,'api');
        $task=ProjectTask::create(['company_id'=>$company->id,'project_id'=>$project->id,'title'=>'Weekly review','assigned_to'=>$user->id,'created_by'=>$user->id,'status'=>'todo','due_date'=>today(),'recurrence_frequency'=>'weekly','recurrence_interval'=>2]);
        $this->putJson("/api/v1/projects/{$project->id}/tasks/{$task->id}",['status'=>'done'])->assertOk();
        $next=ProjectTask::withoutGlobalScopes()->where('generated_from_id',$task->id)->firstOrFail();
        $this->assertSame(today()->addWeeks(2)->toDateString(),$next->due_date->toDateString());
        $this->artisan('projects:run-automations')->assertSuccessful();
        $this->assertSame(1,ProjectTask::withoutGlobalScopes()->where('generated_from_id',$task->id)->count());
    }

    public function test_overdue_automation_notifies_once_and_report_calculates_financials(): void
    {
        [$company,$user,$project]=$this->context(); $this->actingAs($user,'api');
        ProjectAutomationRule::create(['company_id'=>$company->id,'name'=>'Escalate overdue work','trigger'=>'task_overdue','action'=>'notify_user','action_config'=>['user_id'=>$user->id],'is_active'=>true,'created_by'=>$user->id]);
        $task=ProjectTask::create(['company_id'=>$company->id,'project_id'=>$project->id,'title'=>'Late task','assigned_to'=>$user->id,'created_by'=>$user->id,'status'=>'todo','due_date'=>today()->subDay(),'estimated_hours'=>10]);
        ProjectMember::create(['company_id'=>$company->id,'project_id'=>$project->id,'user_id'=>$user->id,'role'=>'owner','allocation_percent'=>100]);
        ProjectTimeEntry::create(['company_id'=>$company->id,'project_id'=>$project->id,'project_task_id'=>$task->id,'user_id'=>$user->id,'work_date'=>today(),'hours'=>2,'billable'=>true,'cost_rate'=>50,'bill_rate'=>120,'status'=>'approved']);
        $this->artisan('projects:run-automations')->assertSuccessful(); $this->artisan('projects:run-automations')->assertSuccessful();
        $this->assertSame(1,$user->notifications()->where('data->kind','project_automation')->count());
        $this->getJson('/api/v1/projects/management-report')->assertOk()->assertJsonPath('data.summary.overdue_tasks',1)
            ->assertJsonPath('data.summary.approved_hours',2)->assertJsonPath('data.summary.actual_cost',100)->assertJsonPath('data.summary.billable_value',240);
    }

    private function context(): array
    {
        $company=Company::create(['name'=>'Tenant','code'=>'TEN','is_active'=>true]);
        $user=User::create(['company_id'=>$company->id,'name'=>'Project User','email'=>'automation@test.local','password'=>'password','is_active'=>true]);
        $project=Project::withoutGlobalScopes()->create(['company_id'=>$company->id,'project_no'=>'PRJ-1','name'=>'Implementation','status'=>'active','owner_id'=>$user->id,'start_date'=>today(),'due_date'=>today()->addDays(20),'budget'=>10000,'currency'=>'USD']);
        return [$company,$user,$project];
    }
}
