<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 CRM-core hygiene fields:
 *  - leads: priority, follow-up date, next action, and a lead-level loss reason (reusing the
 *    existing lost_reasons pick-list already used by deals) so a disqualified lead records WHY.
 *  - contacts: department (job title already exists as `title`).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->string('priority', 16)->default('medium')->after('rating');   // low|medium|high|urgent
            $t->timestamp('follow_up_at')->nullable()->after('last_contacted_at');
            $t->string('next_action', 191)->nullable()->after('follow_up_at');
            $t->foreignId('lost_reason_id')->nullable()->after('status_id')
                ->constrained('lost_reasons')->nullOnDelete();
            $t->index(['company_id', 'follow_up_at']);
        });

        Schema::table('contacts', function (Blueprint $t) {
            $t->string('department', 96)->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->dropConstrainedForeignId('lost_reason_id');
            $t->dropIndex(['company_id', 'follow_up_at']);
            $t->dropColumn(['priority', 'follow_up_at', 'next_action']);
        });
        Schema::table('contacts', function (Blueprint $t) {
            $t->dropColumn('department');
        });
    }
};
