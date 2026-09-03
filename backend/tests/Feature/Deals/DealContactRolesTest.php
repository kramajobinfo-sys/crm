<?php

namespace Tests\Feature\Deals;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealContactRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_deal_can_store_multiple_contacts_with_roles_and_one_primary(): void
    {
        [$company, $user, $stage] = $this->tenant('Tenant A', 'TENANT-A');
        $account = $this->account($company, 'ACC-A');
        $decisionMaker = $this->contact($company, $account, 'Decision Maker');
        $champion = $this->contact($company, $account, 'Champion');
        $this->actingAs($user, 'api');

        $response = $this->postJson('/api/v1/deals', [
            'title' => 'Global rollout',
            'stage_id' => $stage->id,
            'customer_id' => $account->id,
            'contacts' => [
                ['contact_id' => $decisionMaker->id, 'role' => 'decision_maker', 'is_primary' => true],
                ['contact_id' => $champion->id, 'role' => 'champion', 'is_primary' => false],
            ],
        ])->assertCreated()
            ->assertJsonCount(2, 'data.contacts')
            ->assertJsonPath('data.contacts.0.name', 'Decision Maker')
            ->assertJsonPath('data.contacts.0.role', 'decision_maker')
            ->assertJsonPath('data.contacts.0.is_primary', true);

        $dealId = $response->json('data.id');
        $this->assertDatabaseHas('deal_contact', [
            'company_id' => $company->id,
            'deal_id' => $dealId,
            'contact_id' => $champion->id,
            'role' => 'champion',
            'is_primary' => false,
        ]);
    }

    public function test_contact_must_belong_to_the_selected_deal_account(): void
    {
        [$company, $user, $stage] = $this->tenant('Tenant A', 'TENANT-A');
        $selectedAccount = $this->account($company, 'ACC-A');
        $otherAccount = $this->account($company, 'ACC-B');
        $otherContact = $this->contact($company, $otherAccount, 'Other Account Contact');
        $this->actingAs($user, 'api');

        $this->postJson('/api/v1/deals', [
            'title' => 'Invalid relationship',
            'stage_id' => $stage->id,
            'customer_id' => $selectedAccount->id,
            'contacts' => [['contact_id' => $otherContact->id, 'role' => 'influencer']],
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Every Deal Contact must belong to the selected Account.');

        $this->assertSame(0, Deal::count());
    }

    public function test_foreign_tenant_contact_is_rejected_by_validation(): void
    {
        [$companyA, $userA, $stageA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');
        $accountA = $this->account($companyA, 'ACC-A');
        $accountB = $this->account($companyB, 'ACC-B');
        $foreignContact = $this->contact($companyB, $accountB, 'Foreign Contact');
        $this->actingAs($userA, 'api');

        $this->postJson('/api/v1/deals', [
            'title' => 'Cross tenant attempt',
            'stage_id' => $stageA->id,
            'customer_id' => $accountA->id,
            'contacts' => [['contact_id' => $foreignContact->id, 'role' => 'other']],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('contacts.0.contact_id');
    }

    public function test_changing_account_without_contact_payload_detaches_stale_roles(): void
    {
        [$company, $user, $stage] = $this->tenant('Tenant A', 'TENANT-A');
        $accountA = $this->account($company, 'ACC-A');
        $accountB = $this->account($company, 'ACC-B');
        $contact = $this->contact($company, $accountA, 'Account A Contact');
        $this->actingAs($user, 'api');

        $dealId = $this->postJson('/api/v1/deals', [
            'title' => 'Move account safely',
            'stage_id' => $stage->id,
            'customer_id' => $accountA->id,
            'contacts' => [['contact_id' => $contact->id, 'role' => 'billing', 'is_primary' => true]],
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/deals/{$dealId}", ['customer_id' => $accountB->id])
            ->assertOk()
            ->assertJsonCount(0, 'data.contacts');

        $this->assertDatabaseMissing('deal_contact', ['deal_id' => $dealId]);
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

    private function contact(Company $company, Customer $account, string $name): Contact
    {
        return Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'customer_id' => $account->id,
            'name' => $name,
        ]);
    }
}
