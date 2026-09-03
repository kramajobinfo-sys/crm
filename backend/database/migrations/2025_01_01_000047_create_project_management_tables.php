<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('project_no', 32);
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('planned');
            $table->string('priority', 16)->default('medium');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'project_no']);
            $table->unique(['company_id', 'deal_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 24)->default('member');
            $table->unsignedTinyInteger('allocation_percent')->default(100);
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 24)->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['company_id', 'project_id', 'status']);
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('project_tasks')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->string('status', 24)->default('todo');
            $table->string('priority', 16)->default('medium');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('estimated_hours', 8, 2)->default(0);
            $table->decimal('actual_hours', 8, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'project_id', 'status']);
            $table->index(['company_id', 'assigned_to', 'status']);
            $table->index(['company_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
