<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_can_only_query_records_from_their_company(): void
    {
        [$companyA, $userA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');

        $leadA = $this->lead($companyA, 'LEAD-A');
        $leadB = $this->lead($companyB, 'LEAD-B');

        $this->actingAs($userA, 'api');

        $this->assertSame([$leadA->id], Lead::query()->pluck('id')->all());
        $this->assertNotNull(Lead::find($leadA->id));
        $this->assertNull(Lead::find($leadB->id));
    }

    public function test_new_tenant_record_automatically_receives_authenticated_company(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $this->actingAs($user, 'api');

        $lead = Lead::create([
            'lead_no' => 'LEAD-AUTO',
            'name' => 'Scoped lead',
        ]);

        $this->assertSame($company->id, $lead->company_id);
        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_platform_admin_can_query_records_across_companies(): void
    {
        [$companyA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');
        [, $platformAdmin] = $this->tenant('Platform', 'PLATFORM', true);

        $leadA = $this->lead($companyA, 'LEAD-A');
        $leadB = $this->lead($companyB, 'LEAD-B');

        $this->actingAs($platformAdmin, 'api');

        $this->assertEqualsCanonicalizing(
            [$leadA->id, $leadB->id],
            Lead::query()->pluck('id')->all(),
        );
    }

    /** @return array{Company, User} */
    private function tenant(string $name, string $code, bool $platformAdmin = false): array
    {
        $company = Company::create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
            'is_platform' => $platformAdmin,
        ]);

        $user = User::create([
            'company_id' => $company->id,
            'name' => $name.' User',
            'email' => strtolower($code).'@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        // is_platform_admin is intentionally NOT mass-assignable (privilege-escalation guard),
        // so set it the same way production code does — via forceFill.
        if ($platformAdmin) {
            $user->forceFill(['is_platform_admin' => true])->save();
        }

        return [$company, $user];
    }

    private function lead(Company $company, string $number): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_no' => $number,
            'name' => $number.' Name',
        ]);
    }
}
