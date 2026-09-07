<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscription pricing for the plan catalogue. A price is keyed by (plan, interval, currency),
 * so a single Starter plan can carry monthly+yearly prices in both USD and KHR. Platform-level
 * (the SaaS owner's pricing), managed from the platform console — not tenant-scoped.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->enum('interval', ['monthly', 'yearly']);
            $t->char('currency', 3);                 // USD | KHR
            $t->decimal('amount', 12, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['plan_id', 'interval', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
