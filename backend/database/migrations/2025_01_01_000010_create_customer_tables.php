<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_groups', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128); $t->string('code', 32);
            $t->decimal('discount_percent', 5, 2)->default(0);
            $t->unsignedInteger('payment_terms_days')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('customer_no', 32);
            $t->string('type', 16)->default('company');      // company | individual
            $t->foreignId('group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('name', 191);
            $t->string('legal_name', 191)->nullable();
            $t->string('email', 191)->nullable(); $t->string('phone', 32)->nullable();
            $t->string('mobile', 32)->nullable(); $t->string('website', 191)->nullable();
            $t->string('tax_id', 64)->nullable();
            $t->char('currency', 3)->nullable();
            $t->decimal('credit_limit', 15, 2)->default(0);
            $t->unsignedInteger('payment_terms_days')->nullable();
            $t->string('status', 16)->default('active');     // active | on_hold | blocked | archived
            $t->text('notes')->nullable();
            // Set when a Lead converts (Module 2). Unconstrained: leads ships next.
            $t->unsignedBigInteger('converted_from_lead_id')->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'customer_no']);
            $t->index(['company_id', 'status', 'created_at']);
            $t->index(['company_id', 'owner_id']);
            $t->index('name');
        });

        Schema::create('contacts', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $t->string('name', 191); $t->string('title', 128)->nullable();
            $t->string('email', 191)->nullable(); $t->string('phone', 32)->nullable();
            $t->string('mobile', 32)->nullable();
            $t->boolean('is_primary')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->index(['customer_id', 'is_primary']);
        });

        // Shared polymorphic table: customers now, vendors/leads/employees later.
        Schema::create('addresses', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('addressable_type', 191); $t->unsignedBigInteger('addressable_id');
            $t->string('type', 16)->default('billing');      // billing | shipping | other
            $t->string('label', 64)->nullable();
            $t->string('line1', 191); $t->string('line2', 191)->nullable();
            $t->string('city', 96)->nullable(); $t->string('state', 96)->nullable();
            $t->string('postal_code', 32)->nullable(); $t->string('country', 96)->nullable();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->index(['addressable_type', 'addressable_id']);
        });

        // Shared polymorphic audit/history feed rendered on any record's detail page.
        Schema::create('timeline_activities', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('subject_type', 191); $t->unsignedBigInteger('subject_id');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 32);                          // note|call|email|meeting|status_change|system
            $t->string('title', 191);
            $t->text('body')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('occurred_at')->nullable();
            $t->timestamps();
            $t->index(['subject_type', 'subject_id', 'occurred_at'], 'timeline_subject_idx');
            $t->index(['company_id', 'type']);
        });
    }
    public function down(): void {
        foreach (['timeline_activities','addresses','contacts','customers','customer_groups'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
