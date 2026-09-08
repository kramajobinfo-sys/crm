<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lead relationships (Tier 2):
 *  - leads.campaign_id → the marketing campaign that generated the lead (attribution → ROI).
 *  - lead_products → products the lead is interested in (trading-company differentiator);
 *    carried onto the opportunity/quote when the lead converts.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->foreignId('campaign_id')->nullable()->after('source_id')
                ->constrained('campaigns')->nullOnDelete();
            $t->index(['company_id', 'campaign_id']);
        });

        Schema::create('lead_products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->decimal('quantity', 15, 2)->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['lead_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_products');
        Schema::table('leads', function (Blueprint $t) {
            $t->dropConstrainedForeignId('campaign_id');
        });
    }
};
