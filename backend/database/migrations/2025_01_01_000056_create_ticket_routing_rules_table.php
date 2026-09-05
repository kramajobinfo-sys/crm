<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->integer('priority')->default(0);          // evaluation order (ascending)
            $table->json('conditions')->nullable();           // [{field, op, value}] — AND
            $table->string('strategy', 20);                   // specific | round_robin | least_busy
            $table->foreignId('assign_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('pool_user_ids')->nullable();        // for round_robin / least_busy
            $table->integer('round_robin_cursor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'is_active', 'priority']);
        });
    }
    public function down(): void { Schema::dropIfExists('ticket_routing_rules'); }
};
