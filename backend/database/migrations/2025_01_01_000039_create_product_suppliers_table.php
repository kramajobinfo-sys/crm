<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product management enrichment (Inventory): a product can list several suppliers (vendors),
 * each with its own supplier SKU / cost / lead time; one may be marked preferred. See
 * docs/INVENTORY_PRODUCT_SCOPE.md.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $t->string('supplier_sku', 64)->nullable();
            $t->decimal('cost', 15, 2)->default(0);
            $t->unsignedSmallInteger('lead_time_days')->nullable();
            $t->char('currency', 3)->nullable();
            $t->boolean('is_preferred')->default(false);
            $t->timestamps();
            $t->unique(['product_id', 'vendor_id']);            // one row per vendor per product
            $t->index(['company_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
