<?php

namespace Tests\Feature\Crm;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use App\Services\RecordMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_matches_are_tenant_scoped_and_rank_exact_email_high(): void
    {
        [$companyA, $userA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');
        $own = $this->lead($companyA, 'LEAD-A', 'shared@example.test');
        $this->lead($companyB, 'LEAD-B', 'shared@example.test');
        $this->actingAs($userA, 'api');

        $matches = app(DuplicateDetectionService::class)->check('lead', [
            'email' => 'shared@example.test',
        ]);

        $this->assertCount(1, $matches);
        $this->assertSame($own->id, $matches[0]['id']);
        $this->assertSame('high', $matches[0]['confidence']);
        $this->assertSame(['email'], $matches[0]['reasons']);
    }

    public function test_account_matching_reports_tax_id_and_name_reasons(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $account = $this->account($company, 'ACC-A', 'Acme Global', 'TAX-100');
        $this->actingAs($user, 'api');

        $matches = app(DuplicateDetectionService::class)->check('account', [
            'name' => 'Acme Global',
            'tax_id' => 'TAX-100',
        ]);

        $this->assertSame($account->id, $matches[0]['id']);
        $this->assertSame('high', $matches[0]['confidence']);
        $this->assertEqualsCanonicalizing(['tax_id', 'name'], $matches[0]['reasons']);
    }

    public function test_contact_can_match_by_name_within_the_same_account(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $account = $this->account($company, 'ACC-A', 'Acme Global');
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'customer_id' => $account->id,
            'name' => 'Amina Saleh',
        ]);
        $this->actingAs($user, 'api');

        $matches = app(DuplicateDetectionService::class)->check('contact', [
            'name' => 'Amina Saleh',
            'customer_id' => $account->id,
        ]);

        $this->assertSame($contact->id, $matches[0]['id']);
        $this->assertSame(['name_account'], $matches[0]['reasons']);
    }

    public function test_editing_excludes_the_current_record(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $lead = $this->lead($company, 'LEAD-A', 'amina@example.test');
        $this->actingAs($user, 'api');

        $matches = app(DuplicateDetectionService::class)->check('lead', [
            'email' => 'amina@example.test',
        ], $lead->id);

        $this->assertSame([], $matches);
    }

    public function test_account_merge_keeps_selected_fields_moves_contacts_and_writes_audit(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $primary = $this->account($company, 'ACC-A', 'Acme', 'TAX-A');
        $duplicate = $this->account($company, 'ACC-B', 'Acme Corporation', 'TAX-B');
        $contact = Contact::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'customer_id' => $duplicate->id, 'name' => 'Amina Saleh',
        ]);
        $this->actingAs($user, 'api');

        $preview = app(RecordMergeService::class)->preview('account', $primary->id, $duplicate->id);
        $this->assertSame(1, $preview['relationships']['contacts']);

        app(RecordMergeService::class)->merge('account', $primary->id, $duplicate->id, ['name' => 'duplicate']);

        $this->assertSame('Acme Corporation', $primary->fresh()->name);
        $this->assertSame($primary->id, $contact->fresh()->customer_id);
        $this->assertSoftDeleted('customers', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id, 'auditable_id' => $primary->id, 'event' => 'merged',
        ]);
    }

    public function test_merge_rejects_records_from_different_tenants(): void
    {
        [$companyA, $userA] = $this->tenant('Tenant A', 'TENANT-A');
        [$companyB] = $this->tenant('Tenant B', 'TENANT-B');
        $primary = $this->lead($companyA, 'LEAD-A', 'a@example.test');
        $foreign = $this->lead($companyB, 'LEAD-B', 'b@example.test');
        $this->actingAs($userA, 'api');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(RecordMergeService::class)->preview('lead', $primary->id, $foreign->id);
    }

    public function test_contact_merge_requires_the_same_account(): void
    {
        [$company, $user] = $this->tenant('Tenant A', 'TENANT-A');
        $accountA = $this->account($company, 'ACC-A', 'Acme A');
        $accountB = $this->account($company, 'ACC-B', 'Acme B');
        $primary = Contact::withoutGlobalScopes()->create(['company_id' => $company->id, 'customer_id' => $accountA->id, 'name' => 'Amina']);
        $duplicate = Contact::withoutGlobalScopes()->create(['company_id' => $company->id, 'customer_id' => $accountB->id, 'name' => 'Amina']);
        $this->actingAs($user, 'api');

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(RecordMergeService::class)->preview('contact', $primary->id, $duplicate->id);
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

    private function lead(Company $company, string $number, string $email): Lead
    {
        return Lead::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'lead_no' => $number,
            'name' => $number.' Lead',
            'company_name' => 'Acme',
            'email' => $email,
            'rating' => 'warm',
        ]);
    }

    private function account(Company $company, string $number, string $name, ?string $taxId = null): Customer
    {
        return Customer::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'customer_no' => $number,
            'name' => $name,
            'tax_id' => $taxId,
            'type' => 'company',
            'status' => 'active',
        ]);
    }
}
