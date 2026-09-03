<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zoho gap #7 — Price Books. Per-customer / segment / currency price lists.
 *
 * Convenience model (see docs/PRICE_BOOKS_SCOPE.md): a book resolves a *suggested* unit_price the
 * sales line editor pre-fills; the existing quote/order/invoice write paths are untouched and a
 * line price stays client-editable. Every attachment column is nullable, so all existing records
 * and the current pricing behaviour are unchanged until a book is created and attached.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('price_books', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->char('currency', 3);                 // a book prices in exactly one currency
            $t->string('description', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['company_id', 'is_active']);
            $t->index(['company_id', 'currency']);
        });

        Schema::create('price_book_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('price_book_id')->constrained('price_books')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->decimal('unit_price', 15, 2)->default(0);   // absolute price (decision: not discount-off-list)
            $t->timestamps();
            // One price per product per book; the resolver relies on this.
            $t->unique(['price_book_id', 'product_id']);
            $t->index('product_id');
        });

        // Attachment points — nullable, so nothing existing changes until a book is assigned.
        Schema::table('customers', function (Blueprint $t) {
            $t->foreignId('price_book_id')->nullable()->after('currency')
                ->constrained('price_books')->nullOnDelete();
        });
        Schema::table('customer_groups', function (Blueprint $t) {
            $t->foreignId('price_book_id')->nullable()->after('discount_percent')
                ->constrained('price_books')->nullOnDelete();
        });

        // Provenance on each sales document: which book was used to price its lines. Not a
        // re-resolution trigger — lines keep their own unit_price (frozen at entry / on conversion).
        foreach (['quotations', 'sales_orders', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('price_book_id')->nullable()->after('currency')
                    ->constrained('price_books')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['quotations', 'sales_orders', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('price_book_id');
            });
        }
        Schema::table('customer_groups', function (Blueprint $t) {
            $t->dropConstrainedForeignId('price_book_id');
        });
        Schema::table('customers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('price_book_id');
        });
        Schema::dropIfExists('price_book_entries');
        Schema::dropIfExists('price_books');
    }
};
