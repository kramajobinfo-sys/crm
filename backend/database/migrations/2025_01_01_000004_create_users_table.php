<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->string('name', 191);
            $t->string('email', 191);
            $t->string('password');
            $t->string('phone', 32)->nullable();
            $t->string('avatar_path')->nullable();
            $t->char('language', 2)->default('en');
            $t->string('timezone', 64)->default('UTC');
            $t->boolean('is_active')->default(true);
            $t->boolean('two_factor_enabled')->default(false);
            $t->text('two_factor_secret')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'email']);
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email', 191)->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
