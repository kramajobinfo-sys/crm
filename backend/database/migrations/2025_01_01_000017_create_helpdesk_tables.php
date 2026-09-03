<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 10 — Helpdesk. tickets.status (new|open|pending) and tickets.due_at are read by
 * DashboardService for the open-tickets and SLA-breaching KPIs; keep those names.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('ticket_categories', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128); $t->string('code', 32);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('sla_policies', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('priority', 12);                          // low|medium|high|urgent — matched to the ticket
            $t->unsignedInteger('first_response_minutes')->default(240);
            $t->unsignedInteger('resolution_minutes')->default(1440);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'priority']);
        });

        Schema::create('tickets', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('ticket_no', 32);
            $t->string('subject', 191);
            $t->text('description')->nullable();
            $t->string('status', 16)->default('new');            // new|open|pending|resolved|closed
            $t->string('priority', 12)->default('medium');       // low|medium|high|urgent
            $t->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('requester_name', 191)->nullable();       // when not a known customer
            $t->string('requester_email', 191)->nullable();
            $t->string('channel', 16)->default('manual');        // email|phone|web|chat|manual
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('sla_policy_id')->nullable()->constrained('sla_policies')->nullOnDelete();
            $t->timestamp('first_response_due_at')->nullable();
            $t->timestamp('due_at')->nullable();                 // resolution deadline (read by dashboard)
            $t->timestamp('first_response_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->unsignedInteger('reopened_count')->default(0);
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'ticket_no']);
            $t->index(['company_id', 'status', 'due_at']);
            $t->index(['company_id', 'assigned_to']);
        });

        Schema::create('ticket_replies', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('author_type', 16)->default('agent');     // agent|customer|system
            $t->boolean('is_internal')->default(false);          // internal note, hidden from the requester
            $t->text('body');
            $t->timestamps();
            $t->index(['company_id', 'ticket_id']);
        });

        Schema::create('escalations', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $t->unsignedTinyInteger('level')->default(1);
            $t->string('reason', 191)->nullable();
            $t->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('note')->nullable();
            $t->timestamp('escalated_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'ticket_id']);
        });
    }
    public function down(): void {
        foreach (['escalations','ticket_replies','tickets','sla_policies','ticket_categories'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
