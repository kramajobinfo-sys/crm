<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_days')->default(0);
            $table->string('default_priority', 16)->default('medium');
            $table->decimal('default_budget', 15, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->json('blueprint');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('project_tasks', function (Blueprint $table) {
            $table->string('recurrence_frequency', 16)->nullable()->after('overdue_reminder_sent_at');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_frequency');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_interval');
            $table->foreignId('generated_from_id')->nullable()->after('recurrence_end_date')->constrained('project_tasks')->nullOnDelete();
            $table->dateTime('recurrence_generated_at')->nullable()->after('generated_from_id');
            $table->index(['company_id', 'status', 'recurrence_frequency'], 'project_tasks_recurrence_index');
        });

        Schema::create('project_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('trigger', 32);
            $table->json('conditions')->nullable();
            $table->string('action', 32);
            $table->json('action_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'trigger', 'is_active']);
        });

        Schema::create('project_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_automation_rule_id')->constrained('project_automation_rules')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('project_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->string('event_key', 191);
            $table->string('status', 16)->default('completed');
            $table->json('details')->nullable();
            $table->timestamps();
            $table->unique(['project_automation_rule_id', 'event_key'], 'project_automation_runs_event_unique');
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_automation_runs');
        Schema::dropIfExists('project_automation_rules');
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dropIndex('project_tasks_recurrence_index');
            $table->dropConstrainedForeignId('generated_from_id');
            $table->dropColumn(['recurrence_frequency', 'recurrence_interval', 'recurrence_end_date', 'recurrence_generated_at']);
        });
        Schema::dropIfExists('project_templates');
    }
};
