<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('project_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['company_id','project_task_id','created_at'], 'project_task_comment_timeline_idx');
        });

        Schema::create('project_task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('project_tasks')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['task_id','depends_on_task_id']);
            $table->index(['company_id','depends_on_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_task_dependencies');
        Schema::dropIfExists('project_task_comments');
    }
};
