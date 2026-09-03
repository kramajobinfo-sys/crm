<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 8 — Purchase. Vendors, the purchase_request → purchase_order flow, and a generic
 * approval engine (workflows / requests / actions) that can gate either document type.
 * Receiving a PO posts stock movements into inventory (M9).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('vendors', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('vendor_no', 32);
            $t->string('name', 191); $t->string('legal_name', 191)->nullable();
            $t->string('email', 191)->nullable(); $t->string('phone', 32)->nullable();
            $t->string('mobile', 32)->nullable(); $t->string('website', 191)->nullable();
            $t->string('tax_id', 64)->nullable();
            $t->char('currency', 3)->nullable();
            $t->unsignedInteger('payment_terms_days')->nullable();
            $t->string('address', 500)->nullable();
            $t->string('contact_name', 128)->nullable();
            $t->string('status', 16)->default('active');       // active|on_hold|blocked
            $t->text('notes')->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'vendor_no']);
            $t->index(['company_id', 'status']);
            $t->index('name');
        });

        Schema::create('purchase_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('pr_no', 32);
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            // draft|submitted|approved|rejected|converted|cancelled
            $t->string('status', 16)->default('draft');
            $t->date('needed_by')->nullable();
            $t->decimal('estimated_total', 15, 2)->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('converted_po_id')->nullable();       // FK added after purchase_orders exists
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'pr_no']);
            $t->index(['company_id', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('name', 191);
            $t->decimal('quantity', 15, 2)->default(1);
            $t->decimal('estimated_price', 15, 2)->default(0);
            $t->string('note', 500)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['company_id', 'purchase_request_id']);
        });

        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('po_no', 32);
            $t->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $t->foreignId('purchase_request_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();  // receive into
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // draft|submitted|confirmed|received|closed|cancelled. Dashboard purchase chart
            // reads status in (confirmed,received,closed) with order_date + grand_total.
            $t->string('status', 16)->default('draft');
            $t->date('order_date');
            $t->date('expected_date')->nullable();
            $t->char('currency', 3)->nullable();
            $t->decimal('subtotal', 15, 2)->default(0);
            $t->decimal('discount_total', 15, 2)->default(0);
            $t->decimal('tax_total', 15, 2)->default(0);
            $t->decimal('grand_total', 15, 2)->default(0);
            $t->timestamp('received_at')->nullable();
            $t->text('notes')->nullable(); $t->text('terms')->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'po_no']);
            $t->index(['company_id', 'status', 'order_date']);
            $t->index(['company_id', 'vendor_id']);
        });

        // purchase_requests.converted_po_id can now reference purchase_orders.
        Schema::table('purchase_requests', function (Blueprint $t) {
            $t->foreign('converted_po_id')->references('id')->on('purchase_orders')->nullOnDelete();
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('name', 191);
            $t->string('description', 500)->nullable();
            $t->decimal('quantity', 15, 2)->default(1);
            $t->decimal('received_quantity', 15, 2)->default(0);   // how much has been received
            $t->decimal('unit_price', 15, 2)->default(0);
            $t->decimal('discount_pct', 5, 2)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('line_total', 15, 2)->default(0);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index('product_id');
        });

        // --- Generic approval engine -----------------------------------
        Schema::create('approval_workflows', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('document_type', 32);                    // purchase_request | purchase_order
            $t->decimal('min_amount', 15, 2)->default(0);       // applies at or above this total
            $t->json('approver_ids');                           // ordered [userId, …]
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['company_id', 'document_type', 'is_active']);
        });

        Schema::create('approval_requests', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('workflow_id')->nullable()->constrained('approval_workflows')->nullOnDelete();
            $t->morphs('approvable');                            // purchase_request / purchase_order
            $t->string('status', 16)->default('pending');       // pending|approved|rejected
            $t->unsignedInteger('current_step')->default(0);    // index into approver_ids
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'status']);
        });

        Schema::create('approval_actions', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('approval_request_id')->constrained('approval_requests')->cascadeOnDelete();
            $t->unsignedInteger('step');
            $t->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action', 16);                           // approve|reject
            $t->text('comment')->nullable();
            $t->timestamp('acted_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'approval_request_id']);
        });
    }
    public function down(): void {
        foreach ([
            'approval_actions','approval_requests','approval_workflows',
            'purchase_order_items','purchase_orders',
            'purchase_request_items','purchase_requests','vendors',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
