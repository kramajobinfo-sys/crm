<?php

namespace Tests\Feature\Leads;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadConversionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_conversion_creates_account_contact_and_optional_deal_atomically(): void
    {
        [$company, $user, $stage] = $this->tenant('Tenant A', 'TENANT-A');
        $lead = $this->lead($company, 'LEAD-A');
        $this->actingAs($user, 'api');

        $response = $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'account_mode' => 'new',
            'account' => ['name' => 'Acme Global', 'type' => 'company'],
            'create_contact' => true,
            'contact' => ['name' => 'Amina Saleh', 'title' => 'Commercial Director'],
            'create_deal' => true,
            'deal' => ['title' => 'Acme Expansion', 'stage_id' => $stage->id, 'amount' => 75000],
        ])->assertCreated()
            ->assertJsonPath('data.customer.name', 'Acme Global')
            ->assertJsonPath('data.contact.name', 'Amina Saleh')
            ->assertJsonPath('data.deal.title', 'Acme Expansion')
            ->assertJsonPath('data.lead.is_converted', true);

        $accountId = $response->json('data.customer.id');
        $contactId = $response->json('data.contact.id');
        $dealId = $response->json('data.deal.id');
        $this->assertDatabaseHas('contacts', ['id' => $contactId, 'customer_id' => $accountId, 'is_primary' => true]);
        $this->assertDatabaseHas('deals', ['id' => $dealId, 'customer_id' => $accountId, 'lead_id' => $lead->id]);
        $this->assertDatabaseHas('deal_contact', [
            'deal_id' => $dealId,
            'contact_id' => $contactId,
            'role' => 'decision_maker',
            'is_primary' => true,
        ]);
    }

    public function test_conversion_can_attach_to_an_existing_account_without_creating_another(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $lead = $this->lead($company, 'LEAD-A');
        $account = $this->account($company, 'ACC-A');
        $this->actingAs($user, 'api');

        $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'account_mode' => 'existing',
            'account_id' => $account->id,
            'create_contact' => true,
            'create_deal' => false,
        ])->assertCreated()
            ->assertJsonPath('data.customer.id', $account->id)
            ->assertJsonPath('data.contact.customer_id', $account->id)
            ->assertJsonPath('data.deal', null);

        $this->assertSame(1, Customer::count());
    }

    public function test_conversion_rejects_an_account_from_another_tenant(): void
    {
        [$companyA, $userA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');
        $lead = $this->lead($companyA, 'LEAD-A');
        $foreignAccount = $this->account($companyB, 'ACC-B');
        $this->actingAs($userA, 'api');

        $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'account_mode' => 'existing',
            'account_id' => $foreignAccount->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('account_id');

        $this->assertNull($lead->fresh()->converted_to_customer_id);
    }

    public function test_an_already_converted_lead_cannot_create_duplicates(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $lead = $this->lead($company, 'LEAD-A');
        $this->actingAs($user, 'api');

        $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'account_mode' => 'new',
            'create_contact' => true,
        ])->assertCreated();
        $this->postJson("/api/v1/leads/{$lead->id}/convert", [
            'account_mode' => 'new',
            'create_contact' => true,
        ])->assertUnprocessable();

        $this->assertSame(1, Customer::count());
    }

    /** @return array{Company, User, PipelineStage} */
    private function tenant(string $name, string $code): array
    {
        $company = Company::create(['name' => $name, 'code' => $code, 'is_active' => true]);
        $user = User::create([
            'company_id' => $company->id,
            'name' => $name.' User',
            'email' => strtolower($code).'@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $pipeline = Pipeline::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => 'Sales',
            'code' => 'SALES',
            'is_default' => true,
            'is_active' => true,
        ]);
        $stage = PipelineStage::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'pipeline_id' => $pipeline->id,
            'name' => 'Qualification',
            'code' => 'QUALIFY',
            'order_index' => 1,
            'probability' => 20,
        ]);
        return [$company, $user, $stage];
    }

    private function lead(Company $company, string $number): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_no' => $number,
            'name' => 'Amina Saleh',
            'company_name' => 'Acme',
            'email' => 'amina@example.test',
            'estimated_value' => 50000,
            'rating' => 'warm',
        ]);
    }

    private function account(Company $company, string $number): Customer
    {
        return Customer::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'customer_no' => $number,
            'name' => $number.' Account',
            'type' => 'company',
            'status' => 'active',
        ]);
    }
}
