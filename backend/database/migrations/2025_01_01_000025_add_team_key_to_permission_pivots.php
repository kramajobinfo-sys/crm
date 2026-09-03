<?php
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the master/tenant split: turn on spatie "teams" so roles are per-company.
 *
 * The roles table already carries company_id (team_foreign_key). This adds the same team key to
 * the two morph pivots (model_has_roles / model_has_permissions) and rebuilds their primary keys
 * to include it, then migrates existing data:
 *   - every current role becomes owned by the Krama master org (the only tenant with data),
 *   - each assignment row is stamped with its user's company_id,
 *   - the legacy global "Super Admin" role is dropped — its scope-bypass now rides on the
 *     users.is_platform_admin flag (Phase 0), and the tenant owner role is "Owner".
 *
 * The morph pivots' role_id / permission_id foreign keys depend on the PRIMARY index, so each FK
 * is dropped before its primary key is rebuilt and re-created afterwards.
 */
return new class extends Migration {
    public function up(): void
    {
        $krama = DB::table('companies')->where('code', 'KRAMA')->value('id');
        $userType = User::class;

        // SQLite is used by the isolated automated test suite. It cannot execute MySQL's
        // UPDATE ... JOIN or rebuild composite primary keys with ALTER TABLE. Fresh test
        // databases contain no legacy assignments to backfill, so adding the team columns and
        // equivalent uniqueness/index constraints is sufficient. Production MySQL continues
        // through the original migration path below without any behaviour change.
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('model_has_roles', function (Blueprint $t) {
                $t->unsignedBigInteger('company_id')->nullable();
                $t->index('company_id', 'model_has_roles_company_id_index');
                $t->unique(
                    ['company_id', 'role_id', 'model_id', 'model_type'],
                    'model_has_roles_team_unique',
                );
            });
            Schema::table('model_has_permissions', function (Blueprint $t) {
                $t->unsignedBigInteger('company_id')->nullable();
                $t->index('company_id', 'model_has_permissions_company_id_index');
                $t->unique(
                    ['company_id', 'permission_id', 'model_id', 'model_type'],
                    'model_has_permissions_team_unique',
                );
            });
            DB::table('roles')->whereNull('company_id')->update(['company_id' => $krama]);
            return;
        }

        // --- model_has_roles ---
        Schema::table('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->nullable()->after('role_id');
        });
        DB::statement(
            'UPDATE model_has_roles mr JOIN users u ON u.id = mr.model_id SET mr.company_id = u.company_id WHERE mr.model_type = ?',
            [$userType]
        );
        DB::table('model_has_roles')->whereNull('company_id')->update(['company_id' => $krama]);
        DB::statement('ALTER TABLE model_has_roles DROP FOREIGN KEY model_has_roles_role_id_foreign');
        DB::statement('ALTER TABLE model_has_roles DROP PRIMARY KEY');
        DB::statement('ALTER TABLE model_has_roles MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE model_has_roles ADD PRIMARY KEY (company_id, role_id, model_id, model_type)');
        DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');
        DB::statement('CREATE INDEX model_has_roles_company_id_index ON model_has_roles (company_id)');

        // --- model_has_permissions ---
        Schema::table('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->nullable()->after('permission_id');
        });
        DB::statement(
            'UPDATE model_has_permissions mp JOIN users u ON u.id = mp.model_id SET mp.company_id = u.company_id WHERE mp.model_type = ?',
            [$userType]
        );
        DB::table('model_has_permissions')->whereNull('company_id')->update(['company_id' => $krama]);
        DB::statement('ALTER TABLE model_has_permissions DROP FOREIGN KEY model_has_permissions_permission_id_foreign');
        DB::statement('ALTER TABLE model_has_permissions DROP PRIMARY KEY');
        DB::statement('ALTER TABLE model_has_permissions MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE model_has_permissions ADD PRIMARY KEY (company_id, permission_id, model_id, model_type)');
        DB::statement('ALTER TABLE model_has_permissions ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE');
        DB::statement('CREATE INDEX model_has_permissions_company_id_index ON model_has_permissions (company_id)');

        // --- roles: scope every currently-global role to the Krama tenant (no null-team roles must
        //     remain, or they would leak into every company) ---
        DB::table('roles')->whereNull('company_id')->update(['company_id' => $krama]);

        // --- drop the legacy "Super Admin" role; its bypass is now the platform flag ---
        $superAdminIds = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'api')->pluck('id');
        if ($superAdminIds->isNotEmpty()) {
            DB::table('model_has_roles')->whereIn('role_id', $superAdminIds)->delete();
            DB::table('role_has_permissions')->whereIn('role_id', $superAdminIds)->delete();
            DB::table('roles')->whereIn('id', $superAdminIds)->delete();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('model_has_roles', function (Blueprint $t) {
                $t->dropUnique('model_has_roles_team_unique');
                $t->dropIndex('model_has_roles_company_id_index');
                $t->dropColumn('company_id');
            });
            Schema::table('model_has_permissions', function (Blueprint $t) {
                $t->dropUnique('model_has_permissions_team_unique');
                $t->dropIndex('model_has_permissions_company_id_index');
                $t->dropColumn('company_id');
            });
            return;
        }

        DB::statement('ALTER TABLE model_has_roles DROP FOREIGN KEY model_has_roles_role_id_foreign');
        DB::statement('ALTER TABLE model_has_roles DROP PRIMARY KEY');
        DB::statement('DROP INDEX model_has_roles_company_id_index ON model_has_roles');
        Schema::table('model_has_roles', fn (Blueprint $t) => $t->dropColumn('company_id'));
        DB::statement('ALTER TABLE model_has_roles ADD PRIMARY KEY (role_id, model_id, model_type)');
        DB::statement('ALTER TABLE model_has_roles ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE');

        DB::statement('ALTER TABLE model_has_permissions DROP FOREIGN KEY model_has_permissions_permission_id_foreign');
        DB::statement('ALTER TABLE model_has_permissions DROP PRIMARY KEY');
        DB::statement('DROP INDEX model_has_permissions_company_id_index ON model_has_permissions');
        Schema::table('model_has_permissions', fn (Blueprint $t) => $t->dropColumn('company_id'));
        DB::statement('ALTER TABLE model_has_permissions ADD PRIMARY KEY (permission_id, model_id, model_type)');
        DB::statement('ALTER TABLE model_has_permissions ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE');
    }
};
