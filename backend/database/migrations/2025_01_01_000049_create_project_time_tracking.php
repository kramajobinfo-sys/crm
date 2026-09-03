<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->decimal('cost_rate',12,2)->default(0)->after('allocation_percent');
            $table->decimal('bill_rate',12,2)->default(0)->after('cost_rate');
        });
        Schema::table('project_tasks', function (Blueprint $table) {
            $table->dateTime('due_reminder_sent_at')->nullable()->after('completed_at');
            $table->dateTime('overdue_reminder_sent_at')->nullable()->after('due_reminder_sent_at');
        });
        Schema::create('project_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('project_task_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date');
            $table->decimal('hours',6,2);
            $table->boolean('billable')->default(true);
            $table->decimal('cost_rate',12,2)->default(0);
            $table->decimal('bill_rate',12,2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status',16)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id','project_id','work_date']);
            $table->index(['company_id','user_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_time_entries');
        Schema::table('project_tasks', fn(Blueprint $table)=>$table->dropColumn(['due_reminder_sent_at','overdue_reminder_sent_at']));
        Schema::table('project_members', fn(Blueprint $table)=>$table->dropColumn(['cost_rate','bill_rate']));
    }
};
