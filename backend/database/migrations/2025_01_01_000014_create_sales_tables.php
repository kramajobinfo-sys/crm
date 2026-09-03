<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 7 — Sales. Brings products / tax_rates (which Inventory and Purchase depend on)
 * plus the quote → order → invoice → payment document chain.
 *
 * Also closes the FK deferred by M4: deal_products.product_id can now point at products.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('product_categories', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128); $t->string('code', 32);
            $t->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('tax_rates', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 96); $t->string('code', 32);
            $t->decimal('rate', 6, 3)->default(0);            // percent, e.g. 5.000
            $t->boolean('is_inclusive')->default(false);      // price already includes tax
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('sku', 64); $t->string('name', 191);
            $t->text('description')->nullable();
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->string('type', 16)->default('goods');         // goods | service
            $t->string('unit', 24)->default('pcs');
            $t->decimal('cost_price', 15, 2)->default(0);
            $t->decimal('sale_price', 15, 2)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $t->string('barcode', 64)->nullable();
            $t->boolean('track_inventory')->default(true);    // read by M9 Inventory
            $t->decimal('reorder_level', 15, 2)->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'sku']);
            $t->index(['company_id', 'is_active']);
            $t->index('name');
            $t->index('barcode');
        });

        // Now that products exists, close the FK M4 left open on deal_products.
        Schema::table('deal_products', function (Blueprint $t) {
            $t->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        // --- Document chain: quotations → sales_orders → invoices → payments ------
        // Each header carries computed money totals; each *_items row keeps the product
        // name/price denormalised so a line survives a later product edit or delete.

        $this->createDocument('quotations', 'quote_no', function (Blueprint $t) {
            $t->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $t->string('status', 16)->default('draft');       // draft|sent|accepted|rejected|expired|converted
            $t->date('issue_date');
            $t->date('valid_until')->nullable();
            $t->foreignId('converted_order_id')->nullable();  // set on conversion; FK added after sales_orders exists
        });

        $this->createDocument('sales_orders', 'order_no', function (Blueprint $t) {
            $t->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $t->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $t->string('status', 16)->default('draft');       // draft|confirmed|processing|fulfilled|cancelled
            $t->date('order_date');
            $t->date('expected_date')->nullable();
            $t->foreignId('converted_invoice_id')->nullable();
        });

        // quotations.converted_order_id can now point at sales_orders.
        Schema::table('quotations', function (Blueprint $t) {
            $t->foreign('converted_order_id')->references('id')->on('sales_orders')->nullOnDelete();
        });

        $this->createDocument('invoices', 'invoice_no', function (Blueprint $t) {
            $t->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            // created_by + status + issue_date + grand_total are read by DashboardService.
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 16)->default('draft');       // draft|issued|partially_paid|paid|void
            $t->date('issue_date');
            $t->date('due_date')->nullable();
            $t->decimal('amount_paid', 15, 2)->default(0);
            $t->decimal('balance', 15, 2)->default(0);
        });

        // sales_orders.converted_invoice_id can now point at invoices.
        Schema::table('sales_orders', function (Blueprint $t) {
            $t->foreign('converted_invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('payment_no', 32);
            $t->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('method', 24)->default('bank_transfer'); // cash|card|bank_transfer|cheque|online
            $t->decimal('amount', 15, 2)->default(0);
            $t->char('currency', 3)->nullable();
            $t->timestamp('received_at')->nullable();          // read by DashboardService revenue chart
            $t->string('reference', 96)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', 'payment_no']);
            $t->index(['company_id', 'received_at']);
            $t->index(['company_id', 'invoice_id']);
        });
    }

    /** Header + line-items table for one document type, keeping the shared shape in one place. */
    private function createDocument(string $table, string $noColumn, \Closure $extra): void
    {
        Schema::create($table, function (Blueprint $t) use ($noColumn, $extra) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string($noColumn, 32);
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->char('currency', 3)->nullable();
            $t->decimal('subtotal', 15, 2)->default(0);
            $t->decimal('discount_total', 15, 2)->default(0);
            $t->decimal('tax_total', 15, 2)->default(0);
            $t->decimal('grand_total', 15, 2)->default(0);
            $t->text('notes')->nullable();
            $t->text('terms')->nullable();
            $extra($t);                                       // type-specific columns (incl. `status` + a date)
            $t->timestamps(); $t->softDeletes();
            $t->unique(['company_id', $noColumn]);
            $t->index(['company_id', 'customer_id']);
            // Date column differs per type (issue_date / order_date), so the shared index
            // stops at status; each type indexes its own date column separately below.
            $t->index(['company_id', 'status']);
        });

        Schema::create(rtrim($table, 's').'_items', function (Blueprint $t) use ($table) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId($this->fkName($table))->constrained($table)->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();  // soft link; product may be deleted later
            $t->string('name', 191);
            $t->string('description', 500)->nullable();
            $t->decimal('quantity', 15, 2)->default(1);
            $t->decimal('unit_price', 15, 2)->default(0);
            $t->decimal('discount_pct', 5, 2)->default(0);
            $t->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $t->decimal('tax_amount', 15, 2)->default(0);
            $t->decimal('line_total', 15, 2)->default(0);       // net of discount, excl. tax
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index('product_id');
        });
    }

    /** quotations → quotation_id, sales_orders → sales_order_id, invoices → invoice_id. */
    private function fkName(string $table): string
    {
        return rtrim($table, 's').'_id';
    }

    public function down(): void {
        Schema::table('deal_products', function (Blueprint $t) {
            $t->dropForeign(['product_id']);
        });
        foreach ([
            'payments',
            'invoice_items','invoices',
            'sales_order_items','sales_orders',
            'quotation_items','quotations',
            'products','tax_rates','product_categories',
        ] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
