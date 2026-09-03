<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 13 — Reports. User-defined reports over a whitelisted dataset registry (see
 * ReportService), custom dashboards composed of report widgets, and export records.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('saved_reports', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 191);
            $t->string('description', 500)->nullable();
            $t->string('dataset', 48);                           // registry key: deals|invoices|leads|tickets
            $t->string('dimension', 48)->nullable();             // group-by key from the dataset
            $t->json('measures')->nullable();                    // [measure keys]
            $t->json('filters')->nullable();                     // {filter key: value}
            $t->string('chart_type', 16)->default('table');      // table|bar|line|pie
            $t->boolean('is_shared')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'dataset']);
        });

        Schema::create('dashboards', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 191);
            $t->json('layout')->nullable();                      // [{report_id, size}, …]
            $t->boolean('is_default')->default(false);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id']);
        });

        Schema::create('report_exports', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('saved_report_id')->nullable()->constrained('saved_reports')->nullOnDelete();
            $t->string('format', 8)->default('csv');             // csv|pdf|xlsx
            $t->string('status', 12)->default('pending');        // pending|done|failed
            $t->string('disk', 32)->default('public');
            $t->string('path', 500)->nullable();
            $t->unsignedInteger('row_count')->default(0);
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'saved_report_id']);
        });
    }
    public function down(): void {
        foreach (['report_exports','dashboards','saved_reports'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
