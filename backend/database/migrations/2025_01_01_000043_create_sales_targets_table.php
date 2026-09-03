<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoho gap #10 — Forecasts. A quota (target) per user per period (month|quarter); the forecast
 * view compares it against closed-won deals and open pipeline for that period. Distinct from the
 * AI-prediction heuristic — this is target-vs-achieved. See docs/FORECASTS_SCOPE.md.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('period_type', 8);                 // month | quarter
            $t->date('period_start');                      // first day of the month/quarter
            $t->decimal('target_amount', 15, 2)->default(0);
            $t->timestamps();
            $t->unique(['company_id', 'user_id', 'period_type', 'period_start'], 'sales_targets_unique');
            $t->index(['company_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_targets');
    }
};
