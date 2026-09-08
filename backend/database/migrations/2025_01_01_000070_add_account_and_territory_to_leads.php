<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead relationships (Tier 3):
 *  - leads.account_id → an existing Account the lead is associated with BEFORE conversion (common
 *    B2B: "this lead works at customer X"). Reused as the target account on conversion.
 *  - leads.territory → sales-org territory (a filter, like customers.territory — not a boundary).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->foreignId('account_id')->nullable()->after('company_name')
                ->constrained('customers')->nullOnDelete();
            $t->string('territory', 96)->nullable()->after('branch_id');
            $t->index(['company_id', 'territory']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->dropConstrainedForeignId('account_id');
            $t->dropIndex(['company_id', 'territory']);
            $t->dropColumn('territory');
        });
    }
};
