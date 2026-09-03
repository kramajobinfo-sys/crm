<?php

namespace Tests\Feature\Activities;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityRelatedValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
    }

    public function test_task_can_be_linked_to_a_contact_in_the_same_tenant(): void
    {
        [$company, $user, $contact] = $this->tenant('Tenant A', 'TENANT-A');
        $this->actingAs($user, 'api');

        $this->postJson('/api/v1/activities/tasks', [
            'title' => 'Call Amina tomorrow',
            'related_type' => 'contact',
            'related_id' => $contact->id,
        ])->assertCreated()
            ->assertJsonPath('data.related.id', $contact->id);

        $this->assertDatabaseHas('tasks', [
            'company_id' => $company->id,
            'related_type' => Contact::class,
            'related_id' => $contact->id,
        ]);
    }

    public function test_activity_rejects_a_related_record_from_another_tenant(): void
    {
        [, $user] = $this->tenant('Tenant A', 'TENANT-A');
        [, , $foreignContact] = $this->tenant('Tenant B', 'TENANT-B');
        $this->actingAs($user, 'api');

        $this->postJson('/api/v1/activities/tasks', [
            'title' => 'Invalid cross-tenant task',
            'related_type' => 'contact',
            'related_id' => $foreignContact->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('related_id');
    }

    /** @return array{Company, User, Contact} */
    private function tenant(string $name, string $code): array
    {
        $company = Company::create(['name' => $name, 'code' => $code, 'is_active' => true]);
        $user = User::create([
            'company_id' => $company->id, 'name' => $name.' User',
            'email' => strtolower($code).'@example.test', 'password' => 'password', 'is_active' => true,
        ]);
        $account = Customer::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'customer_no' => $code.'-ACC',
            'name' => $name.' Account', 'type' => 'company', 'status' => 'active',
        ]);
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'customer_id' => $account->id, 'name' => $name.' Contact',
        ]);
        return [$company, $user, $contact];
    }
}
