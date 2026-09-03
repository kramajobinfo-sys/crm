<?php
namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanFeature;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Provisions the RBAC baseline for a tenant. The platform owns the *permission vocabulary*
 * (a global catalogue of module.action names); each tenant gets its own copy of a default
 * *role set* it can then clone and edit. This is the master/tenant model: shared vocabulary,
 * per-company composition.
 *
 * Reused by RolePermissionSeeder (existing companies) and self-service registration (new ones).
 */
class TenantProvisioner
{
    /** The permission vocabulary: module => actions. Global — every tenant draws from the same list. */
    public const MODULES = [
        'dashboard'=>['view'],
        'leads'=>['view','create','update','delete','export','assign','convert','score'],
        'customers'=>['view','create','update','delete','export'],
        'contacts'=>['view','create','update','delete','export'],
        'deals'=>['view','create','update','delete','export','change_stage'],
        'pipelines'=>['manage'],
        'activities'=>['view','create','update','delete'],
        'projects'=>['view','create','update','delete','manage_members'],
        'project_tasks'=>['view','create','update','delete','comment','upload'],
        'timesheets'=>['view','create','update','delete','approve'],
        'project_templates'=>['view','create','update','delete'],
        'project_automation'=>['view','create','update','delete'],
        'project_reports'=>['view'],
        'products'=>['view','create','update','delete'],
        'price_books'=>['view','create','update','delete'],
        'email'=>['view','send','manage_templates'],
        'quotations'=>['view','create','update','delete','approve','send'],
        'orders'=>['view','create','update','delete'],
        'invoices'=>['view','create','update','delete','send'],
        'payments'=>['view','create','update','delete'],
        // Customer credits (credit notes). `apply` is separate from `create` on purpose:
        // spending an existing credit against an invoice is a different act from issuing one.
        'credits'=>['view','create','apply','delete'],
        'purchase_requests'=>['view','create','update','delete','approve'],
        'purchase_orders'=>['view','create','update','delete','approve'],
        'vendors'=>['view','create','update','delete'],
        'inventory'=>['view','create','update','delete','transfer','adjust'],
        'warehouses'=>['view','create','update','delete'],
        'manufacturing'=>['view','manage','build'],
        // Website visitor tracking. `manage` is separate from `view` because it reveals and
        // rotates the public site key — rotating silently breaks every deployed snippet.
        'visits'=>['view','manage'],
        'tickets'=>['view','create','update','delete','assign','close'],
        'kb'=>['view','create','update','delete'],
        'chat'=>['view','reply','assign','close','link_crm','manage_channels'],
        'integrations'=>['view','manage','sync'],
        'campaigns'=>['view','create','update','delete','launch'],
        'employees'=>['view','create','update','delete'],
        'attendance'=>['view','create','update','delete'],
        'leave'=>['view','create','update','delete','approve'],
        'reports'=>['view','create','export'],
        'forecasts'=>['view','manage'],
        'documents'=>['view','create','update','delete'],
        'workflows'=>['view','create','update','delete'],
        'ai'=>['use'],
        'settings'=>['view','update'],
        'users'=>['view','create','update','delete'],
        'roles'=>['view','create','update','delete'],
        'audit'=>['view'],
        'api_keys'=>['view','create','update','delete'],
    ];

    /** The tenant's owner role — full access, protected from edit/delete inside the tenant. */
    public const OWNER_ROLE = 'Owner';

    /** Edition a fresh self-service tenant starts on. */
    public const DEFAULT_PLAN = 'starter';

    /** Edition backfilled onto companies that predate the plans system (the Krama master org
     *  included) — full access, so converting to plans never revokes something that already worked. */
    public const EXISTING_COMPANY_PLAN = 'enterprise';

    /** Every permission name in the catalogue. */
    public function permissionNames(): array
    {
        $names = [];
        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) $names[] = "{$module}.{$action}";
        }
        return $names;
    }

    /** Ensure the global permission catalogue exists. Idempotent. */
    public function syncPermissionCatalogue(): void
    {
        foreach (self::MODULES as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name'=>"{$module}.{$action}", 'guard_name'=>'api'], ['module'=>$module]);
            }
        }
    }

    /** The default role set a fresh tenant starts with: role name => permission names ('all' = everything). */
    public function roleDefinitions(): array
    {
        $byModule = fn (array $mods) => Permission::where('guard_name','api')->whereIn('module',$mods)->pluck('name')->toArray();

        return [
            self::OWNER_ROLE => 'all',
            'CEO' => $byModule(array_keys(self::MODULES)),
            // Sales Manager can see and spend a customer's credit, but issuing/voiding credit
            // notes is a finance act (Accountant/CEO/Owner).
            'Sales Manager' => array_merge($byModule(['dashboard','leads','customers','contacts','deals','activities','projects','project_tasks','timesheets','project_templates','project_automation','project_reports','products','price_books','quotations','orders','invoices','payments','reports','forecasts','documents','ai','chat']), ['email.view','email.send','email.manage_templates','kb.view','credits.view','credits.apply','visits.view','visits.manage']),
            'Sales Staff' => ['dashboard.view','leads.view','leads.create','leads.update','leads.convert','customers.view','customers.create','customers.update','contacts.view','contacts.create','contacts.update','deals.view','deals.create','deals.update','deals.change_stage','activities.view','activities.create','activities.update','products.view','price_books.view','quotations.view','quotations.create','quotations.update','quotations.send','orders.view','orders.create','invoices.view','credits.view','email.view','email.send','ai.use','chat.view','chat.reply','chat.link_crm','kb.view','documents.view','documents.create','documents.update','visits.view','forecasts.view'],
            'Purchase Manager' => array_merge($byModule(['dashboard','vendors','purchase_requests','purchase_orders','inventory','reports']), ['products.view','documents.view']),
            'Warehouse' => array_merge($byModule(['dashboard','inventory','warehouses','manufacturing']), ['products.view']),
            'Accountant' => array_merge($byModule(['dashboard','invoices','payments','credits','reports']), ['products.view','price_books.view','quotations.view','orders.view','customers.view','contacts.view','vendors.view','documents.view']),
            'Customer Service' => array_merge($byModule(['dashboard','tickets','kb','chat','customers','contacts','activities','ai']), ['documents.view']),
            'Project Manager' => array_merge($byModule(['dashboard','projects','project_tasks','timesheets','project_templates','project_automation','project_reports','activities','customers','contacts','documents','reports']), ['deals.view']),
            'HR' => array_merge($byModule(['dashboard','employees','attendance','leave','reports']), ['documents.view','documents.create','documents.update','documents.delete']),
        ];
    }

    /**
     * Create (idempotently) the default role set scoped to one company. Runs inside that company's
     * team context so every role row is stamped with its company_id, then restores the prior context.
     */
    public function provisionRoles(Company $company): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($company->id);

        try {
            foreach ($this->roleDefinitions() as $name => $perms) {
                // Include company_id in the match/create attributes — Eloquent's firstOrCreate does
                // not go through spatie's team-stamping create() override, so set it explicitly.
                $role = Role::firstOrCreate(['name'=>$name, 'guard_name'=>'api', 'company_id'=>$company->id]);
                $role->syncPermissions($perms === 'all' ? Permission::where('guard_name','api')->get() : $perms);
            }
        } finally {
            $registrar->setPermissionsTeamId($previous);
            $registrar->forgetCachedPermissions();
        }
    }

    /**
     * The edition catalogue: plan code => module list (from self::MODULES). Cumulative by design
     * — each tier is the one below it plus more, so there's no accidental gap between them.
     */
    public function planDefinitions(): array
    {
        // `credits` is in Starter deliberately, alongside invoices/payments: the system mints a
        // credit whenever a payment overpays an invoice, on every plan. Gating the module out
        // of a tier would leave those tenants with money recorded but no way to see or spend it.
        $starterModules = ['dashboard', 'settings', 'users', 'roles',
            'leads', 'customers', 'contacts', 'deals', 'activities', 'products',
            'quotations', 'orders', 'invoices', 'payments', 'credits', 'ai'];
        $professionalModules = array_merge($starterModules, [
            'price_books', 'projects', 'project_tasks', 'timesheets', 'project_templates', 'project_automation', 'project_reports', 'kb', 'documents', 'visits', 'forecasts',
            'email', 'tickets', 'chat', 'campaigns', 'reports',
            'vendors', 'purchase_requests', 'purchase_orders', 'inventory', 'warehouses', 'api_keys',
        ]);
        $enterpriseModules = array_keys(self::MODULES); // everything

        return [
            'starter' => ['name' => 'Starter', 'sort_order' => 1, 'modules' => $starterModules],
            'professional' => ['name' => 'Professional', 'sort_order' => 2, 'modules' => $professionalModules],
            'enterprise' => ['name' => 'Enterprise', 'sort_order' => 3, 'modules' => $enterpriseModules],
        ];
    }

    /** Ensure the plan catalogue (editions + their module lists) exists and matches the current
     *  definitions above. Idempotent — safe to run on every seed. */
    public function syncPlanCatalogue(): void
    {
        foreach ($this->planDefinitions() as $code => $def) {
            $plan = Plan::firstOrCreate(['code' => $code], ['name' => $def['name'], 'sort_order' => $def['sort_order']]);
            $plan->update(['name' => $def['name'], 'sort_order' => $def['sort_order']]);
            foreach ($def['modules'] as $module) {
                PlanFeature::firstOrCreate(['plan_id' => $plan->id, 'module' => $module]);
            }
            // Drop features no longer in the definition, so edits above take effect on reseed.
            PlanFeature::where('plan_id', $plan->id)->whereNotIn('module', $def['modules'])->delete();
        }
    }

    /** Attach a tenant to an edition by code. */
    public function provisionPlan(Company $company, string $code): void
    {
        $plan = Plan::where('code', $code)->firstOrFail();
        $company->update(['plan_id' => $plan->id]);
    }
}
