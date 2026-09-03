<?php
namespace Database\Seeders;

use App\Models\Company;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the global permission catalogue, then provisions each existing company's own role set.
 * Roles are per-company (spatie teams); the vocabulary is shared. Idempotent.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $provisioner = app(TenantProvisioner::class);
        $provisioner->syncPermissionCatalogue();
        $provisioner->syncPlanCatalogue();

        // Every tenant (including the Krama master org) gets its own copy of the default roles.
        // Companies that predate the plans system are backfilled onto the top edition so
        // converting to plans never silently revokes access to something that already worked.
        foreach (Company::withoutGlobalScopes()->get() as $company) {
            $provisioner->provisionRoles($company);
            if (!$company->plan_id) {
                $provisioner->provisionPlan($company, TenantProvisioner::EXISTING_COMPANY_PLAN);
            }
        }
    }
}
