<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Set when the "follow-up due" reminder for the current follow_up_at has been sent,
            // so the daily sweep notifies the owner exactly once per scheduled follow-up.
            $table->timestamp('follow_up_notified_at')->nullable()->after('follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('follow_up_notified_at');
        });
    }
};
