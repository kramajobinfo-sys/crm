<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 14 — Workflow. A trigger → conditions → ordered-actions automation engine. Workflows
 * run manually or on an event; each run is logged with per-action results. Actions with external
 * effects (email/webhook) are structural.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('workflows', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 191);
            $t->string('description', 500)->nullable();
            $t->string('entity', 48);                            // leads|deals|tickets|customers|invoices
            $t->string('trigger_type', 16)->default('manual');   // manual|event|schedule
            $t->string('trigger_event', 64)->nullable();         // e.g. lead.created, deal.stage_changed
            $t->json('conditions')->nullable();                  // [{field, op, value}] ANDed
            $t->string('schedule_cron', 64)->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('run_count')->default(0);
            $t->timestamp('last_run_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'entity', 'is_active']);
        });

        Schema::create('workflow_actions', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $t->unsignedInteger('order')->default(0);
            $t->string('type', 32);                              // create_task|update_field|send_email|notify|webhook|log
            $t->json('config')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'workflow_id', 'order']);
        });

        Schema::create('workflow_runs', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $t->string('trigger_type', 16)->default('manual');
            $t->string('status', 12)->default('success');        // success|failed|partial|skipped
            $t->nullableMorphs('subject');                       // the record the run acted on
            $t->json('log')->nullable();                         // [{action, type, status, message}]
            $t->unsignedInteger('actions_run')->default(0);
            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'workflow_id']);
        });

        Schema::create('scheduled_jobs', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $t->string('name', 191);
            $t->string('cron', 64);
            $t->boolean('is_active')->default(true);
            $t->timestamp('next_run_at')->nullable();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'is_active']);
        });
    }
    public function down(): void {
        foreach (['scheduled_jobs','workflow_runs','workflow_actions','workflows'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
