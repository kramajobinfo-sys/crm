<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('branches', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 191);
            $t->string('code', 32);
            $t->string('address')->nullable();
            $t->string('city', 100)->nullable();
            $t->string('country', 100)->nullable();
            $t->string('phone', 32)->nullable();
            $t->unsignedBigInteger('manager_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
    }
    public function down(): void { Schema::dropIfExists('branches'); }
};
