<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing / BOM (Phase B). A finished-good product's bill of materials (component products +
 * per-unit quantities) and an immediate, atomic build that consumes components and produces the
 * finished good through the existing signed stock ledger. See docs/MANUFACTURING_BOM_SCOPE.md.
 */
return new class extends Migration {
    public function up(): void
    {
        // Headerless BOM: a product "has a BOM" iff it has >=1 bom_item.
        Schema::create('bom_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();            // finished good
            $t->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();   // a component (also a product)
            $t->decimal('quantity', 15, 4)->default(1);           // per ONE unit of the finished good
            $t->timestamps();
            $t->unique(['product_id', 'component_product_id']);
            $t->index(['company_id', 'product_id']);
        });

        Schema::create('builds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('build_no', 32);
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();     // output
            $t->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $t->decimal('quantity', 15, 2);                       // units produced
            $t->decimal('unit_cost', 15, 2)->default(0);          // rolled-up finished-good cost/unit
            $t->decimal('total_cost', 15, 2)->default(0);
            $t->string('status', 16)->default('completed');       // completed (immediate); kept for future cancel
            $t->text('notes')->nullable();
            $t->foreignId('built_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('built_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['company_id', 'build_no']);
            $t->index(['company_id', 'product_id']);
        });

        Schema::create('build_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('build_id')->constrained('builds')->cascadeOnDelete();
            $t->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $t->string('name', 191);                              // denormalised for display
            $t->decimal('quantity', 15, 4);                       // consumed = per_unit x build qty
            $t->decimal('unit_cost', 15, 2)->default(0);
            $t->timestamps();
            $t->index(['company_id', 'build_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('build_items');
        Schema::dropIfExists('builds');
        Schema::dropIfExists('bom_items');
    }
};
