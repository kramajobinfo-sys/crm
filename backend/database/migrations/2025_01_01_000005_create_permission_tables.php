<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teamKey = $columnNames['team_foreign_key'] ?? 'company_id';

        Schema::create($tableNames['permissions'], function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name', 191);
            $t->string('guard_name', 64);
            $t->string('module', 64)->nullable()->index();
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });
        Schema::create($tableNames['roles'], function (Blueprint $t) use ($teamKey) {
            $t->bigIncrements('id');
            $t->string('name', 191);
            $t->string('guard_name', 64);
            $t->unsignedBigInteger($teamKey)->nullable()->index();
            $t->timestamps();
            $t->unique([$teamKey, 'name', 'guard_name']);
        });
        Schema::create($tableNames['model_has_permissions'], function (Blueprint $t) use ($tableNames, $columnNames) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger($columnNames['model_morph_key']);
            $t->index([$columnNames['model_morph_key'], 'model_type'], 'mhp_model_type_index');
            $t->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $t->primary(['permission_id', $columnNames['model_morph_key'], 'model_type'], 'mhp_primary');
        });
        Schema::create($tableNames['model_has_roles'], function (Blueprint $t) use ($tableNames, $columnNames) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger($columnNames['model_morph_key']);
            $t->index([$columnNames['model_morph_key'], 'model_type'], 'mhr_model_type_index');
            $t->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $t->primary(['role_id', $columnNames['model_morph_key'], 'model_type'], 'mhr_primary');
        });
        Schema::create($tableNames['role_has_permissions'], function (Blueprint $t) use ($tableNames) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->foreign('permission_id')->references('id')->on($tableNames['permissions'])->onDelete('cascade');
            $t->foreign('role_id')->references('id')->on($tableNames['roles'])->onDelete('cascade');
            $t->primary(['permission_id', 'role_id'], 'rhp_primary');
        });
    }
    public function down(): void {
        $tableNames = config('permission.table_names');
        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
