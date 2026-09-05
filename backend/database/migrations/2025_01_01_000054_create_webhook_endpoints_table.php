<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 40);                 // web_to_lead ...
            $table->string('slug', 32)->unique();       // public path segment
            $table->text('secret');                     // encrypted HMAC signing secret
            $table->string('secret_prefix', 16);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('webhook_endpoints'); }
};
