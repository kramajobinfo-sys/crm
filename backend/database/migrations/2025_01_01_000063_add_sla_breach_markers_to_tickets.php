<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Breach markers so the SLA sweep is idempotent: a ticket is acted on (escalation + notification)
 * exactly once when it first breaches, not on every 5-minute run. Cleared whenever deadlines are
 * recomputed (applySla), so a re-breach after an extension is detectable again.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->timestamp('response_breached_at')->nullable()->after('first_response_at');
            $t->timestamp('sla_breached_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $t) {
            $t->dropColumn(['response_breached_at', 'sla_breached_at']);
        });
    }
};
