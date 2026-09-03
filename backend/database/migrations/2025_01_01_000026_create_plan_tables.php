<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the master/tenant conversion: subscriptions/editions.
 *
 * `plans` + `plan_features` are schema-only here — the catalogue itself (which modules each
 * edition includes) lives in TenantProvisioner::planDefinitions(), synced by RolePermissionSeeder,
 * the same pattern already used for the permission vocabulary and default role set.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 64);
            $t->string('description', 191)->nullable();
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->string('module', 64);
            $t->timestamps();
            $t->unique(['plan_id', 'module']);
        });

        Schema::table('companies', function (Blueprint $t) {
            $t->foreignId('plan_id')->nullable()->after('is_platform')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropConstrainedForeignId('plan_id');
        });
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
