<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name', 191);
            $t->string('code', 32)->unique();
            $t->string('legal_name', 191)->nullable();
            $t->string('tax_id', 64)->nullable();
            $t->char('base_currency', 3)->default('USD');
            $t->string('logo_path')->nullable();
            $t->string('primary_color', 16)->default('#185FA5');
            $t->char('default_language', 2)->default('en');
            $t->string('address_line1')->nullable();
            $t->string('address_line2')->nullable();
            $t->string('city', 100)->nullable();
            $t->string('country', 100)->nullable();
            $t->string('phone', 32)->nullable();
            $t->string('email', 191)->nullable();
            $t->string('website', 191)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('is_active');
        });
    }
    public function down(): void { Schema::dropIfExists('companies'); }
};
