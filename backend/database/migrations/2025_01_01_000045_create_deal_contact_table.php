<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('deal_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('role', 32)->default('other');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['deal_id', 'contact_id']);
            $table->index(['company_id', 'deal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_contact');
    }
};
