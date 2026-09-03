<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the master/tenant conversion: platform console.
 *
 * `support_access_grants` is an authorization RECORD, not a session — it establishes that a
 * platform admin was allowed, for a bounded window and a stated reason, to act on a tenant's
 * behalf. It does not currently change what any request can read or write: `BelongsToCompany`'s
 * scope and create-time company_id still resolve from the caller's own `company_id`, and a
 * platform admin already bypasses that scope entirely (sees everything, writes under their own
 * company). Wiring an actual "enter this tenant" context switch means teaching that trait to read
 * an effective-company resolver instead of `auth()->user()->company_id` directly — every model
 * that uses the trait, no test suite to catch regressions — deliberately left for a later phase.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_access_grants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('granted_by')->constrained('users')->cascadeOnDelete();
            $t->string('reason', 255);
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_grants');
    }
};
