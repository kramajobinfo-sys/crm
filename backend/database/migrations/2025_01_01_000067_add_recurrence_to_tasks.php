<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring tasks. A recurring task spawns its next occurrence when it is completed (see
 * ActivityService::spawnNextOccurrence), up to recurrence_until; recurrence_parent_id links the
 * whole series back to the first task.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->string('recurrence', 16)->nullable()->after('due_at');   // daily | weekly | monthly
            $t->date('recurrence_until')->nullable()->after('recurrence');
            $t->foreignId('recurrence_parent_id')->nullable()->after('recurrence_until')
                ->constrained('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropConstrainedForeignId('recurrence_parent_id');
            $t->dropColumn(['recurrence', 'recurrence_until']);
        });
    }
};
