<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 15 — AI Assistant. Structural, no live LLM: a chat surface whose replies are heuristic
 * answers derived from real CRM data, plus insights and predictions generated over existing
 * modules. Insights also feed the dashboard ai-insights widget.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ai_conversations', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title', 191)->default('New conversation');
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'user_id']);
        });

        Schema::create('ai_messages', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $t->string('role', 12);                              // user|assistant
            $t->text('content');
            $t->json('meta')->nullable();                       // data the answer was built from
            $t->timestamps();
            $t->index(['company_id', 'ai_conversation_id']);
        });

        Schema::create('ai_insights', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('type', 48);                              // overdue_invoices|stale_deals|unassigned_hot_leads|sla_breaches|low_stock
            $t->string('level', 12)->default('info');            // info|warning|critical
            $t->string('title', 191);
            $t->text('body')->nullable();
            $t->json('meta')->nullable();
            $t->boolean('is_dismissed')->default(false);
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'is_dismissed', 'level']);
        });

        Schema::create('ai_predictions', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('type', 48);                              // pipeline_forecast|top_lead|churn_risk
            $t->nullableMorphs('subject');
            $t->string('title', 191);
            $t->json('value')->nullable();                       // {score/amount/probability, factors}
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'type']);
        });
    }
    public function down(): void {
        foreach (['ai_predictions','ai_insights','ai_messages','ai_conversations'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
