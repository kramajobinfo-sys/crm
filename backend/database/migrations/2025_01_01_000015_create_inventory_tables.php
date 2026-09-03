<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 9 — Inventory. On-hand is kept as a running balance per (product, warehouse) in
 * stock_items, and every change is written to the stock_movements ledger with a signed
 * quantity and the resulting balance, so the two can always be reconciled.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128); $t->string('code', 32);
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->string('address', 500)->nullable();
            $t->string('contact_name', 128)->nullable();
            $t->string('contact_phone', 32)->nullable();
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('stock_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->decimal('quantity', 15, 2)->default(0);           // on hand
            $t->decimal('reserved_quantity', 15, 2)->default(0);  // committed to orders
            $t->decimal('average_cost', 15, 2)->default(0);
            $t->string('bin_location', 64)->nullable();
            $t->timestamps();
            $t->unique(['product_id', 'warehouse_id']);
            $t->index(['company_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            // receipt|issue|adjustment|transfer_in|transfer_out|sale|purchase
            $t->string('type', 24);
            $t->decimal('quantity', 15, 2);                        // signed delta applied to on-hand
            $t->decimal('balance_after', 15, 2);                   // on-hand snapshot after this move
            $t->decimal('unit_cost', 15, 2)->default(0);
            $t->string('reference', 96)->nullable();
            $t->text('note')->nullable();
            $t->nullableMorphs('related');                         // invoice / PO / transfer, etc.
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('occurred_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'product_id', 'warehouse_id']);
            $t->index(['company_id', 'occurred_at']);
        });

        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('transfer_no', 32);
            $t->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->string('status', 16)->default('draft');            // draft|in_transit|received|cancelled
            $t->date('transfer_date');
            $t->text('notes')->nullable();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'transfer_no']);
            $t->index(['company_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->string('name', 191);                               // denormalised for display
            $t->decimal('quantity', 15, 2)->default(0);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['company_id', 'stock_transfer_id']);
        });

        Schema::create('barcodes', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->string('barcode', 64);
            $t->string('type', 16)->default('EAN13');              // EAN13|UPC|CODE128|QR|custom
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
            $t->unique(['company_id', 'barcode']);
            $t->index(['company_id', 'product_id']);
        });
    }
    public function down(): void {
        foreach (['barcodes','stock_transfer_items','stock_transfers','stock_movements','stock_items','warehouses'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
