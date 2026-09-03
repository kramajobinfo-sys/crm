<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoho gap #11 — SalesInbox (inbound email). Finishes the receive side: `emails.in_reply_to`
 * lets an inbound reply thread onto the outbound message it answers, and `email_accounts`
 * gains an IMAP high-water mark so the fetcher only pulls new messages. IMAP credentials
 * themselves live in the existing encrypted `email_accounts.config`. See docs/SALESINBOX_SCOPE.md.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $t) {
            $t->string('in_reply_to', 191)->nullable()->after('message_id');
            $t->index(['company_id', 'message_id']);   // dedupe lookups on inbound ingest
        });
        Schema::table('email_accounts', function (Blueprint $t) {
            $t->unsignedBigInteger('imap_last_uid')->nullable()->after('config');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'message_id']);
            $t->dropColumn('in_reply_to');
        });
        Schema::table('email_accounts', fn (Blueprint $t) => $t->dropColumn('imap_last_uid'));
    }
};
