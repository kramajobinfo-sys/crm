<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $t) {
            $t->timestamp('signed_at')->nullable()->after('converted_order_id');
            $t->string('signed_name', 191)->nullable()->after('signed_at');
            $t->string('signed_ip', 45)->nullable()->after('signed_name');
            $t->longText('signature_data')->nullable()->after('signed_ip');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $t) {
            $t->dropColumn(['signed_at', 'signed_name', 'signed_ip', 'signature_data']);
        });
    }
};
