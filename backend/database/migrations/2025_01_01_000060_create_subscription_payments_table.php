<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One payment attempt against a subscription. Method is COD, KHQR (Bakong) or ABA KHQR (ABA PayWay).
 * For the QR methods we persist the generated payload / deeplink and the provider reference + md5 that
 * we later poll or match a callback against. `paid_at` flipping non-null is what activates the plan.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('method', ['cod', 'khqr', 'aba_khqr']);
            $t->string('provider', 16)->default('manual');   // manual | bakong | aba
            $t->char('currency', 3)->default('USD');
            $t->decimal('amount', 12, 2)->default(0);
            $t->enum('status', ['pending', 'paid', 'failed', 'canceled', 'refunded'])->default('pending');
            $t->string('provider_ref', 128)->nullable();      // gateway transaction id
            $t->string('md5', 64)->nullable();                // Bakong md5 / PayWay hash to reconcile
            $t->text('qr_payload')->nullable();               // EMV/KHQR string to render
            $t->string('deeplink', 512)->nullable();          // app deeplink (ABA / Bakong)
            $t->string('receipt_path', 255)->nullable();      // member-uploaded proof (manual fallback)
            $t->string('note', 255)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('expires_at')->nullable();          // QR validity window
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status']);
            $t->index('md5');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
