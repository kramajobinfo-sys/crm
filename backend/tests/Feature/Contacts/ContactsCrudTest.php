<?php

namespace Tests\Feature\Contacts;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Authentication, permission, feature and audit middleware have their own boundaries.
        // These tests exercise Contact HTTP CRUD while retaining Eloquent tenant scoping.
        $this->withoutMiddleware();
    }

    public function test_contact_crud_round_trip(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $account = $this->account($company, 'ACC-A');
        $this->actingAs($user, 'api');

        $created = $this->postJson('/api/v1/contacts', [
            'customer_id' => $account->id,
            'name' => 'Amina Saleh',
            'title' => 'Procurement Manager',
            'email' => 'amina@example.test',
            'is_primary' => true,
        ])->assertCreated()
            ->assertJsonPath('data.account.name', $account->name)
            ->assertJsonPath('data.name', 'Amina Saleh');

        $id = $created->json('data.id');

        $this->getJson("/api/v1/contacts/{$id}")
            ->assertOk()
            ->assertJsonPath('data.email', 'amina@example.test');

        $this->putJson("/api/v1/contacts/{$id}", ['title' => 'Commercial Director'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Commercial Director');

        $this->deleteJson("/api/v1/contacts/{$id}")->assertOk();
        $this->assertSoftDeleted('contacts', ['id' => $id]);
    }

    public function test_contact_list_and_record_actions_are_tenant_scoped(): void
    {
        [$companyA, $userA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');
        $accountA = $this->account($companyA, 'ACC-A');
        $accountB = $this->account($companyB, 'ACC-B');
        $contactA = $this->contact($companyA, $accountA, 'Tenant A Contact');
        $contactB = $this->contact($companyB, $accountB, 'Tenant B Contact');
        $this->actingAs($userA, 'api');

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $contactA->id);

        $this->getJson("/api/v1/contacts/{$contactB->id}")->assertNotFound();
        $this->putJson("/api/v1/contacts/{$contactB->id}", ['name' => 'Changed'])->assertNotFound();
        $this->deleteJson("/api/v1/contacts/{$contactB->id}")->assertNotFound();
    }

    public function test_contact_cannot_be_assigned_to_another_tenants_account(): void
    {
        [$companyA, $userA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');
        $foreignAccount = $this->account($companyB, 'ACC-B');
        $this->actingAs($userA, 'api');

        $this->postJson('/api/v1/contacts', [
            'customer_id' => $foreignAccount->id,
            'name' => 'Invalid Contact',
        ])->assertUnprocessable()->assertJsonValidationErrors('customer_id');

        $this->assertSame(0, Contact::withoutGlobalScopes()->where('company_id', $companyA->id)->count());
    }

    public function test_only_one_primary_contact_is_kept_per_account(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $account = $this->account($company, 'ACC-A');
        $this->actingAs($user, 'api');

        $firstId = $this->postJson('/api/v1/contacts', [
            'customer_id' => $account->id,
            'name' => 'First Primary',
            'is_primary' => true,
        ])->assertCreated()->json('data.id');

        $secondId = $this->postJson('/api/v1/contacts', [
            'customer_id' => $account->id,
            'name' => 'Second Primary',
            'is_primary' => true,
        ])->assertCreated()->json('data.id');

        $this->assertFalse(Contact::findOrFail($firstId)->is_primary);
        $this->assertTrue(Contact::findOrFail($secondId)->is_primary);
    }

    /** @return array{Company, User} */
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
        return [$company, $user];
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
