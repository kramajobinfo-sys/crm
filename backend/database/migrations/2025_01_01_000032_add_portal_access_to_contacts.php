<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->string('password')->nullable()->after('notes');
            $t->boolean('portal_enabled')->default(false)->after('password');
            $t->timestamp('last_login_at')->nullable()->after('portal_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->dropColumn(['password', 'portal_enabled', 'last_login_at']);
        });
    }
};
