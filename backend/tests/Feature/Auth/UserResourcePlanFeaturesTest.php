<?php

namespace Tests\Feature\Auth;

use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourcePlanFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_payload_exposes_the_tenants_plan_modules_for_navigation(): void
    {
        $plan = Plan::create([
            'code' => 'crm-only',
            'name' => 'CRM Only',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $plan->features()->createMany([
            ['module' => 'dashboard'],
            ['module' => 'leads'],
            ['module' => 'customers'],
        ]);
        $company = Company::create([
            'name' => 'Tenant A',
            'code' => 'TENANT-A',
            'plan_id' => $plan->id,
            'is_active' => true,
        ]);
        $user = User::create([
            'company_id' => $company->id,
            'name' => 'Workspace User',
            'email' => 'workspace@example.test',
            'password' => 'password',
            'is_active' => true,
        ])->load(['company.plan.features', 'branch', 'department']);

        $payload = (new UserResource($user))->resolve(request());

        $this->assertSame('crm-only', $payload['company']['plan']['code']);
        $this->assertEqualsCanonicalizing(
            ['dashboard', 'leads', 'customers'],
            $payload['company']['plan']['modules']->all(),
        );
    }
}
