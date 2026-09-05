<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('event_id', 128);            // for idempotency/dedup
            $table->string('status', 16)->default('received'); // received|processed|failed
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->unique(['webhook_endpoint_id', 'event_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('webhook_events'); }
};
