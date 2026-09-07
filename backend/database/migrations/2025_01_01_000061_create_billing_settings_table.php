<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Singleton platform billing configuration (one row, id=1). Holds which payment methods are enabled,
 * the COD instructions shown to members, and — encrypted — the Bakong / ABA PayWay merchant
 * credentials. These are meant to be filled in later from the platform console; until a method's
 * credentials exist it stays in manual-confirm mode, and disabled methods are hidden at checkout.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $t) {
            $t->id();
            $t->json('enabled')->nullable();          // {"cod":true,"khqr":false,"aba_khqr":false}
            $t->text('cod_instructions')->nullable();
            $t->text('config')->nullable();           // encrypted:array — gateway credentials
            $t->timestamps();
        });

        // Seed the singleton with COD on by default (works with no credentials).
        DB::table('billing_settings')->insert([
            'id' => 1,
            'enabled' => json_encode(['cod' => true, 'khqr' => false, 'aba_khqr' => false]),
            'cod_instructions' => 'Pay in cash on delivery. Your subscription activates once our team confirms payment.',
            'config' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
