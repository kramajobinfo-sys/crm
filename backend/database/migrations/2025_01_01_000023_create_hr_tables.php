<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 12 — HR. Employees, attendance, and a leave flow: a request computes its days and is
 * checked against the employee's balance; approval deducts from that balance.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('employees', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('employee_no', 32);
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();  // self-service login link
            $t->string('first_name', 96); $t->string('last_name', 96)->nullable();
            $t->string('email', 191)->nullable(); $t->string('phone', 32)->nullable();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();  // self-ref
            $t->string('job_title', 128)->nullable();
            $t->string('employment_type', 16)->default('full_time'); // full_time|part_time|contract|intern
            $t->string('status', 16)->default('active');             // active|on_leave|terminated
            $t->date('hire_date')->nullable();
            $t->date('termination_date')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('national_id', 64)->nullable();
            $t->decimal('salary', 15, 2)->default(0);
            $t->char('currency', 3)->nullable();
            $t->string('address', 500)->nullable();
            $t->string('emergency_contact', 191)->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'employee_no']);
            $t->index(['company_id', 'status']);
            $t->index(['company_id', 'department_id']);
        });

        Schema::create('leave_types', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 96); $t->string('code', 32);
            $t->decimal('days_per_year', 6, 1)->default(0);          // default entitlement
            $t->boolean('is_paid')->default(true);
            $t->string('color', 16)->default('#64748B');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('attendances', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->date('date');
            $t->time('check_in')->nullable();
            $t->time('check_out')->nullable();
            // present|absent|late|half_day|leave|holiday|weekend
            $t->string('status', 12)->default('present');
            $t->decimal('hours_worked', 5, 2)->default(0);
            $t->string('notes', 255)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'date']);
            $t->index(['company_id', 'date']);
        });

        Schema::create('leave_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $t->date('start_date'); $t->date('end_date');
            $t->decimal('days', 6, 1)->default(0);
            $t->string('reason', 500)->nullable();
            $t->string('status', 16)->default('pending');            // pending|approved|rejected|cancelled
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->string('decision_note', 500)->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->index(['company_id', 'status']);
            $t->index(['company_id', 'employee_id']);
        });

        Schema::create('leave_balances', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $t->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
            $t->unsignedSmallInteger('year');
            $t->decimal('entitled', 6, 1)->default(0);
            $t->decimal('used', 6, 1)->default(0);
            $t->timestamps();
            $t->unique(['employee_id', 'leave_type_id', 'year']);
            $t->index(['company_id', 'year']);
        });
    }
    public function down(): void {
        foreach (['leave_balances','leave_requests','attendances','leave_types','employees'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
