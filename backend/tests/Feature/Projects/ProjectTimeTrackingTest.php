<?php

namespace Tests\Feature\Projects;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->withoutMiddleware(); }

    public function test_time_approval_snapshots_rates_and_calculates_project_financials(): void
    {
        [$company,$user,$project]=$this->context(); $this->actingAs($user,'api');
        ProjectMember::create(['company_id'=>$company->id,'project_id'=>$project->id,'user_id'=>$user->id,'role'=>'owner','allocation_percent'=>100,'cost_rate'=>40,'bill_rate'=>100]);
        $task=ProjectTask::create(['company_id'=>$company->id,'project_id'=>$project->id,'title'=>'Implementation','assigned_to'=>$user->id,'created_by'=>$user->id,'status'=>'todo','estimated_hours'=>16]);

        $entryId=$this->postJson("/api/v1/projects/{$project->id}/time-entries",['project_task_id'=>$task->id,'work_date'=>today()->toDateString(),'hours'=>2.5,'billable'=>true,'status'=>'submitted'])
            ->assertCreated()->assertJsonPath('data.cost_rate',40)->assertJsonPath('data.bill_rate',100)->json('data.id');
        $this->postJson("/api/v1/projects/{$project->id}/time-entries/{$entryId}/decision",['decision'=>'approved'])->assertOk()->assertJsonPath('data.status','approved');
        $this->getJson("/api/v1/projects/{$project->id}/time-entries")->assertOk()->assertJsonPath('data.summary.approved_hours',2.5)->assertJsonPath('data.summary.actual_cost',100)->assertJsonPath('data.summary.billable_value',250);
        $this->getJson("/api/v1/projects/{$project->id}")->assertOk()->assertJsonPath('data.tasks.0.actual_hours',2.5);
        $this->putJson("/api/v1/projects/{$project->id}/time-entries/{$entryId}",['hours'=>3])->assertUnprocessable()->assertJsonPath('message','Approved time entries cannot be changed.');
    }

    public function test_workload_reports_capacity_and_planned_hours(): void
    {
        [$company,$user,$project]=$this->context(); $this->actingAs($user,'api');
        ProjectMember::create(['company_id'=>$company->id,'project_id'=>$project->id,'user_id'=>$user->id,'role'=>'owner','allocation_percent'=>50]);
        ProjectTask::create(['company_id'=>$company->id,'project_id'=>$project->id,'title'=>'Planned work','assigned_to'=>$user->id,'created_by'=>$user->id,'status'=>'todo','due_date'=>today()->addDays(3),'estimated_hours'=>20]);
        $this->getJson('/api/v1/projects/workload?from='.today()->toDateString().'&to='.today()->addDays(6)->toDateString())
            ->assertOk()->assertJsonPath('data.items.0.planned_hours',20)->assertJsonPath('data.items.0.allocation_percent',50);
    }

    public function test_due_task_notifications_are_sent_once(): void
    {
        [$company,$user,$project]=$this->context();
        ProjectTask::withoutGlobalScopes()->create(['company_id'=>$company->id,'project_id'=>$project->id,'title'=>'Submit design','assigned_to'=>$user->id,'created_by'=>$user->id,'status'=>'todo','due_date'=>today()->addDay()]);
        $this->artisan('projects:dispatch-task-notifications')->assertSuccessful();
        $this->assertSame(1,$user->notifications()->count());
        $this->artisan('projects:dispatch-task-notifications')->assertSuccessful();
        $this->assertSame(1,$user->notifications()->count());
        $this->assertSame('due_soon',$user->notifications()->first()->data['kind']);
    }

    private function context(): array
    {
        $company=Company::create(['name'=>'Tenant','code'=>'TEN','is_active'=>true]);
        $user=User::create(['company_id'=>$company->id,'name'=>'Project User','email'=>'project@test.local','password'=>'password','is_active'=>true]);
        $project=Project::withoutGlobalScopes()->create(['company_id'=>$company->id,'project_no'=>'PRJ-1','name'=>'Implementation','status'=>'active','owner_id'=>$user->id,'budget'=>10000,'currency'=>'USD']);
        return [$company,$user,$project];
    }
}
