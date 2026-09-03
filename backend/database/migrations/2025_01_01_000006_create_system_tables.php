<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('currencies', function (Blueprint $t) {
            $t->id(); $t->char('code', 3)->unique(); $t->string('name', 64); $t->string('symbol', 8);
            $t->decimal('exchange_rate', 18, 8)->default(1); $t->boolean('is_base')->default(false);
            $t->timestamp('last_updated_at')->nullable(); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('key', 128); $t->json('value')->nullable(); $t->timestamp('updated_at')->useCurrent();
            $t->unique(['company_id', 'key']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('auditable_type', 191); $t->unsignedBigInteger('auditable_id'); $t->string('event', 32);
            $t->json('old_values')->nullable(); $t->json('new_values')->nullable();
            $t->string('url', 500)->nullable(); $t->string('ip_address', 45)->nullable(); $t->string('user_agent', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['auditable_type', 'auditable_id']); $t->index(['company_id', 'created_at']);
        });
        Schema::create('login_history', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('ip_address', 45)->nullable(); $t->string('user_agent', 500)->nullable();
            $t->string('location_country', 100)->nullable(); $t->string('location_city', 100)->nullable();
            $t->string('status', 16); $t->string('failure_reason', 191)->nullable(); $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
        });
        Schema::create('dashboard_widgets', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('widget_type', 64); $t->integer('position')->default(0);
            $t->json('config')->nullable(); $t->boolean('is_visible')->default(true); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('type'); $t->morphs('notifiable');
            $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::create('jobs', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->string('queue', 128)->index(); $t->longText('payload');
            $t->unsignedTinyInteger('attempts'); $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at'); $t->unsignedInteger('created_at');
        });
        Schema::create('job_batches', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('name'); $t->integer('total_jobs');
            $t->integer('pending_jobs'); $t->integer('failed_jobs'); $t->longText('failed_job_ids');
            $t->mediumText('options')->nullable(); $t->integer('cancelled_at')->nullable();
            $t->integer('created_at'); $t->integer('finished_at')->nullable();
        });
        Schema::create('failed_jobs', function (Blueprint $t) {
            $t->id(); $t->string('uuid')->unique(); $t->text('connection'); $t->text('queue');
            $t->longText('payload'); $t->longText('exception'); $t->timestamp('failed_at')->useCurrent();
        });
        Schema::create('cache', function (Blueprint $t) {
            $t->string('key')->primary(); $t->mediumText('value'); $t->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $t) {
            $t->string('key')->primary(); $t->string('owner'); $t->integer('expiration');
        });
    }
    public function down(): void {
        foreach (['cache_locks','cache','failed_jobs','job_batches','jobs','notifications',
            'dashboard_widgets','login_history','audit_logs','settings','currencies'] as $tbl) Schema::dropIfExists($tbl);
    }
};
