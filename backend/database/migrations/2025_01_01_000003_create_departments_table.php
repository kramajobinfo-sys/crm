<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('name', 191);
            $t->string('code', 32);
            $t->unsignedBigInteger('head_id')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
    }
    public function down(): void { Schema::dropIfExists('departments'); }
};
