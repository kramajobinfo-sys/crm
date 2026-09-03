<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('chat_channels', function (Blueprint $t) {
            // A company runs several accounts per provider (5 Facebook pages, 3 IG, …).
            // external_account_id stays NULLable — webchat and sms have no provider account id —
            // but MySQL permits unlimited NULLs in a UNIQUE index, so that index alone does not
            // stop duplicates. Display name is the constraint that actually holds.
            $t->unique(['company_id', 'type', 'name'], 'chat_channels_name_unique');
        });
        Schema::table('chat_message_attachments', function (Blueprint $t) {
            $t->string('thumbnail_path', 500)->nullable()->after('path');
            $t->unsignedInteger('width')->nullable()->after('size');
            $t->unsignedInteger('height')->nullable()->after('width');
            $t->unsignedInteger('duration_seconds')->nullable()->after('height');
        });
    }
    public function down(): void {
        Schema::table('chat_channels', function (Blueprint $t) {
            $t->dropUnique('chat_channels_name_unique');
        });
        Schema::table('chat_message_attachments', function (Blueprint $t) {
            $t->dropColumn(['thumbnail_path', 'width', 'height', 'duration_seconds']);
        });
    }
};
