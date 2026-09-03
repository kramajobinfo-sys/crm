<?php

namespace Tests\Feature\Dashboard;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MyWorkTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_queue_combines_only_the_users_crm_and_project_tasks(): void
    {
        $this->withoutMiddleware();
        $company=Company::create(['name'=>'Tenant','code'=>'TEN','is_active'=>true]);
        $user=User::create(['company_id'=>$company->id,'name'=>'Assigned User','email'=>'assigned@test.local','password'=>'password','is_active'=>true]);
        $other=User::create(['company_id'=>$company->id,'name'=>'Other User','email'=>'other@test.local','password'=>'password','is_active'=>true]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        foreach (['activities.view','project_tasks.view'] as $name) Permission::create(['name'=>$name,'guard_name'=>'api','module'=>strtok($name,'.')]);
        $user->givePermissionTo(['activities.view','project_tasks.view']);
        $this->actingAs($user,'api');

        Task::create(['company_id'=>$company->id,'title'=>'Call customer','assigned_to'=>$user->id,'created_by'=>$user->id,'status'=>'open','priority'=>'high','due_at'=>now()]);
        Task::create(['company_id'=>$company->id,'title'=>'Someone else','assigned_to'=>$other->id,'created_by'=>$user->id,'status'=>'open']);
        $project=Project::create(['company_id'=>$company->id,'project_no'=>'PRJ-1','name'=>'Delivery','owner_id'=>$user->id]);
        ProjectTask::create(['company_id'=>$company->id,'project_id'=>$project->id,'title'=>'Configure system','assigned_to'=>$user->id,'created_by'=>$user->id,'status'=>'todo']);

        $this->getJson('/api/v1/my-work')->assertOk()->assertJsonCount(2,'data.items')
            ->assertJsonPath('data.summary.open',2);
        $this->assertEqualsCanonicalizing(['crm','project'],collect($this->getJson('/api/v1/my-work')->json('data.items'))->pluck('source')->all());
    }
}
