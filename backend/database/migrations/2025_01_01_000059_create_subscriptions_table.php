<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A tenant company's subscription to a plan edition. The active row (status=active with a period
 * still open) is the one that gates modules; older rows are kept as history. Amount/interval/currency
 * are snapshotted at checkout so later price changes never rewrite what a member agreed to pay.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('interval', ['monthly', 'yearly'])->default('monthly');
            $t->char('currency', 3)->default('USD');
            $t->decimal('amount', 12, 2)->default(0);
            $t->enum('status', ['pending', 'active', 'past_due', 'canceled', 'expired'])->default('pending');
            $t->date('current_period_start')->nullable();
            $t->date('current_period_end')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('canceled_at')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
