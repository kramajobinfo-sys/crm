<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the "structural only" gap noted in the original Module 6 migration: an email account
 * can now hold real SMTP credentials (encrypted, same pattern as chat_channels.config), and a
 * failed send has somewhere to record why.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $t) {
            $t->text('config')->nullable()->after('provider');
        });
        Schema::table('emails', function (Blueprint $t) {
            $t->string('error', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', fn (Blueprint $t) => $t->dropColumn('config'));
        Schema::table('emails', fn (Blueprint $t) => $t->dropColumn('error'));
    }
};
