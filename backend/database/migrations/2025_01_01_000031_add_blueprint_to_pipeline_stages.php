<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blueprint-style guided sales process (Zoho-style): a stage can require certain deal fields be
 * filled before a deal enters it, and can restrict which stages it may move to next. Both are
 * nullable/empty by default — every existing seeded pipeline keeps working unrestricted until an
 * admin opts a stage into either rule via PUT /pipelines/{id}/stages/{id}.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('pipeline_stages', function (Blueprint $t) {
            $t->json('required_fields')->nullable()->after('probability');
            $t->json('allowed_next_stage_ids')->nullable()->after('required_fields');
        });
    }
    public function down(): void {
        Schema::table('pipeline_stages', function (Blueprint $t) {
            $t->dropColumn(['required_fields', 'allowed_next_stage_ids']);
        });
    }
};
