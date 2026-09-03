<?php
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 of the master/tenant split: separate the platform owner from a tenant owner.
 *
 * Until now a single "Super Admin" role both ran the app and bypassed the company scope.
 * We move that scope-bypass onto explicit flags — `companies.is_platform` marks the Krama
 * master org, `users.is_platform_admin` marks Krama staff — so a tenant's own top role never
 * carries cross-company reach. No tenant behaviour changes here; `teams` stays off.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->boolean('is_platform')->default(false)->after('is_active');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_platform_admin')->default(false)->after('is_active');
        });

        // Mark the Krama master org.
        DB::table('companies')->where('code', 'KRAMA')->update(['is_platform' => true]);

        // Promote whoever currently holds the global "Super Admin" role to platform admin —
        // the scope-bypass now rides on this flag, not on the role name.
        $roleId = DB::table('roles')->where('name', 'Super Admin')->where('guard_name', 'api')->value('id');
        if ($roleId) {
            $userIds = DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', User::class)
                ->pluck('model_id');
            if ($userIds->isNotEmpty()) {
                DB::table('users')->whereIn('id', $userIds)->update(['is_platform_admin' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('is_platform'));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_platform_admin'));
    }
};
