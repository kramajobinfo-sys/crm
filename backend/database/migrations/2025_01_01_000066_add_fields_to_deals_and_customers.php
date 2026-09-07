<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 field additions:
 *  - deals: competitor (who we're up against) and forecast_category (pipeline/best_case/commit/omitted),
 *    the standard sales-forecast roll-up dimension.
 *  - customers: territory (a sales-org filter, NOT an access boundary) and tags (free-form json list).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $t) {
            $t->string('competitor', 191)->nullable()->after('source');
            $t->string('forecast_category', 24)->default('pipeline')->after('probability');
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->string('territory', 96)->nullable()->after('branch_id');
            $t->json('tags')->nullable()->after('territory');
            $t->index(['company_id', 'territory']);
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $t) {
            $t->dropColumn(['competitor', 'forecast_category']);
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'territory']);
            $t->dropColumn(['territory', 'tags']);
        });
    }
};
