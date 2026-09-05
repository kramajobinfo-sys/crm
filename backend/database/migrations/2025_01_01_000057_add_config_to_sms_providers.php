<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sms_providers', function (Blueprint $table) {
            $table->text('config')->nullable()->after('sender_id');   // encrypted credentials
        });
    }
    public function down(): void
    {
        Schema::table('sms_providers', function (Blueprint $table) { $table->dropColumn('config'); });
    }
};
