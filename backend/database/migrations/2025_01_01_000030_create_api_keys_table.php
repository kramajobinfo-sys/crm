<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API keys let a third-party integration call the same /api/v1 surface a human JWT session would,
 * without a login flow. A key resolves to a real `user_id` — it acts with that user's roles and
 * permissions, so RBAC, company scoping, and audit stamping all work unchanged (see
 * JwtAuthenticate::handleApiKey()). Only the hash is stored; the plaintext key is shown once.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('api_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 191);
            $t->string('key_prefix', 12);   // shown in the UI list for identification
            $t->string('key_hash', 64);     // sha256 hex of the full key; never the key itself
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
            $t->unique('key_hash');
            $t->index(['company_id', 'is_active']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('api_keys');
    }
};
