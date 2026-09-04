<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contact_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('channel', 32);          // email | sms | phone | whatsapp | marketing
            $table->string('status', 16);           // granted | withdrawn
            $table->string('source', 64)->nullable(); // web_form | import | agent | portal | api ...
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'contact_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_consents');
    }
};
