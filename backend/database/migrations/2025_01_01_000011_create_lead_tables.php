<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('lead_sources', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 96); $t->string('code', 32);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('lead_statuses', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 96); $t->string('code', 32);
            $t->string('color', 16)->default('#64748B');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_won')->default(false);
            $t->boolean('is_lost')->default(false);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('lead_no', 32);
            $t->string('name', 191);                       // the person
            $t->string('company_name', 191)->nullable();   // their organisation, if any
            $t->string('title', 128)->nullable();
            $t->string('email', 191)->nullable(); $t->string('phone', 32)->nullable();
            $t->string('mobile', 32)->nullable(); $t->string('website', 191)->nullable();
            $t->foreignId('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $t->foreignId('status_id')->nullable()->constrained('lead_statuses')->nullOnDelete();
            $t->unsignedTinyInteger('score')->default(0);  // 0-100, recomputed by leads:score
            $t->string('rating', 16)->default('cold');     // hot|warm|cold — derived from score
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->decimal('estimated_value', 15, 2)->default(0);
            $t->char('currency', 3)->nullable();
            $t->date('expected_close_date')->nullable();
            $t->timestamp('last_contacted_at')->nullable();
            // customers exists now, so unlike the reverse column this one can be constrained.
            $t->foreignId('converted_to_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->timestamp('converted_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'lead_no']);
            $t->index(['company_id', 'status_id', 'created_at']);
            $t->index(['company_id', 'owner_id']);
            $t->index(['company_id', 'score']);
            $t->index('name');
        });

        // Close the loop now that leads exists. Both sides are nullable, so the
        // circular reference is legal and cannot deadlock inserts.
        Schema::table('customers', function (Blueprint $t) {
            $t->foreign('converted_from_lead_id')->references('id')->on('leads')->nullOnDelete();
        });

        Schema::create('lead_assignment_rules', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->unsignedInteger('priority')->default(0);   // lower runs first
            $t->json('conditions')->nullable();            // [{field, op, value}, …] ANDed
            $t->string('strategy', 24)->default('specific'); // specific | round_robin
            $t->foreignId('assign_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('round_robin_user_ids')->nullable();
            $t->unsignedBigInteger('round_robin_cursor')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['company_id', 'is_active', 'priority']);
        });

        // Shared polymorphic attachments — leads now, deals/tickets/quotes later.
        // Same reasoning as `addresses` and `timeline_activities`: one table, not one per module.
        Schema::create('attachments', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('attachable_type', 191); $t->unsignedBigInteger('attachable_id');
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('disk', 32)->default('public');
            $t->string('path', 500);
            $t->string('name', 191); $t->string('mime', 128)->nullable();
            $t->unsignedBigInteger('size')->default(0);
            $t->timestamps();
            $t->index(['attachable_type', 'attachable_id']);
        });
    }
    public function down(): void {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropForeign(['converted_from_lead_id']);
        });
        foreach (['attachments','lead_assignment_rules','leads','lead_statuses','lead_sources'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
