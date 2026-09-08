<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-channel consent ledger for leads (mirrors contact_consents). Append-only history; the latest
 * row per lead+channel is authoritative. Marketing suppression consults it (via ContactConsent::
 * suppression) so a lead who opts out is never emailed/SMS'd — closing the "market to leads without
 * consent" gap.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('lead_consents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $t->string('channel', 32);              // email | sms | phone | whatsapp | marketing
            $t->string('status', 16);               // granted | withdrawn
            $t->string('source', 64)->nullable();
            $t->string('note', 255)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('occurred_at');
            $t->timestamps();
            $t->index(['company_id', 'lead_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_consents');
    }
};
