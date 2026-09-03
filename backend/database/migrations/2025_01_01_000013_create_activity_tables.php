<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        // Column contract from DashboardService::tasksSummary(): assigned_to, status
        // (must include 'open' and 'in_progress'), due_at, title, priority.
        Schema::create('tasks', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('title', 191);
            $t->text('description')->nullable();
            $t->string('status', 16)->default('open');        // open | in_progress | done | cancelled
            $t->string('priority', 12)->default('medium');    // low | medium | high | urgent
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('due_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            // Polymorphic link to the record this activity is about (deal/lead/customer).
            $t->nullableMorphs('related');
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'assigned_to', 'status']);
            $t->index(['company_id', 'due_at']);
        });

        Schema::create('meetings', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('title', 191);
            $t->text('description')->nullable();
            $t->string('location', 191)->nullable();
            $t->string('meeting_link', 500)->nullable();
            $t->string('status', 16)->default('scheduled');   // scheduled | completed | cancelled
            $t->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('start_at');
            $t->timestamp('end_at')->nullable();
            $t->nullableMorphs('related');
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'start_at']);
            $t->index(['company_id', 'organizer_id']);
        });

        Schema::create('meeting_participants', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // External guests carry a name/email instead of a user row.
            $t->string('name', 191)->nullable();
            $t->string('email', 191)->nullable();
            $t->string('response', 16)->default('invited');   // invited | accepted | declined | tentative
            $t->timestamps();
            $t->index(['company_id', 'meeting_id']);
        });

        Schema::create('calls', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('subject', 191);
            $t->string('direction', 12)->default('outbound');  // inbound | outbound
            $t->string('status', 16)->default('completed');    // scheduled | completed | missed | cancelled
            $t->string('phone', 32)->nullable();
            $t->unsignedInteger('duration_seconds')->default(0);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('notes')->nullable();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('occurred_at')->nullable();
            $t->nullableMorphs('related');
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'user_id']);
            $t->index(['company_id', 'occurred_at']);
        });

        Schema::create('reminders', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title', 191);
            $t->timestamp('remind_at');
            $t->string('channel', 16)->default('in_app');      // in_app | email
            $t->boolean('is_sent')->default(false);
            $t->timestamp('sent_at')->nullable();
            // Optional link to the activity/record this reminder is for.
            $t->nullableMorphs('related');
            $t->timestamps();
            $t->index(['company_id', 'user_id', 'is_sent', 'remind_at']);
        });
    }
    public function down(): void {
        foreach (['reminders','calls','meeting_participants','meetings','tasks'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
