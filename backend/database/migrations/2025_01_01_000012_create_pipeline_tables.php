<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('pipelines', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 96); $t->string('code', 32);
            $t->boolean('is_default')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        // Column is `order_index` (not `sort_order`) because DashboardService::pipeline()
        // groups/orders by pipeline_stages.order_index. Do not rename without touching it.
        Schema::create('pipeline_stages', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $t->string('name', 96); $t->string('code', 32);
            $t->string('color', 16)->default('#64748B');
            $t->unsignedInteger('order_index')->default(0);
            $t->unsignedTinyInteger('probability')->default(0);   // default win-likelihood for deals landing here
            $t->boolean('is_won')->default(false);
            $t->boolean('is_lost')->default(false);
            $t->timestamps();
            $t->unique(['pipeline_id', 'code']);
            $t->index(['company_id', 'pipeline_id', 'order_index']);
        });

        Schema::create('lost_reasons', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128); $t->string('code', 32);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });

        Schema::create('deals', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('deal_no', 32);
            $t->string('title', 191);
            $t->foreignId('pipeline_id')->constrained('pipelines')->cascadeOnDelete();
            $t->foreignId('stage_id')->constrained('pipeline_stages')->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();     // where the deal came from
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $t->decimal('amount', 15, 2)->default(0);
            $t->char('currency', 3)->nullable();
            $t->unsignedTinyInteger('probability')->default(0);    // 0-100, seeded from the stage, editable
            // Materialised convenience: stage.is_won/is_lost is the source of truth; the
            // service rewrites this whenever the stage changes so the two never disagree.
            $t->string('status', 12)->default('open');             // open | won | lost
            $t->date('expected_close_date')->nullable();
            $t->timestamp('won_at')->nullable();
            $t->timestamp('lost_at')->nullable();
            $t->foreignId('lost_reason_id')->nullable()->constrained('lost_reasons')->nullOnDelete();
            $t->string('source', 96)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps(); $t->softDeletes();                   // pipeline() joins on whereNull(deleted_at)
            $t->unique(['company_id', 'deal_no']);
            $t->index(['company_id', 'pipeline_id', 'stage_id']);
            $t->index(['company_id', 'status', 'expected_close_date']);
            $t->index(['company_id', 'owner_id']);
        });

        // product_id is left unconstrained until M7 (Sales) creates `products`; the FK is
        // added in that migration, mirroring how customers.converted_from_lead_id was closed.
        // name/unit_price keep the line meaningful even if the product is later deleted.
        Schema::create('deal_products', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('deal_id')->constrained('deals')->cascadeOnDelete();
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('name', 191);
            $t->string('description', 500)->nullable();
            $t->decimal('quantity', 15, 2)->default(1);
            $t->decimal('unit_price', 15, 2)->default(0);
            $t->decimal('discount_pct', 5, 2)->default(0);
            $t->decimal('line_total', 15, 2)->default(0);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->index(['company_id', 'deal_id']);
            $t->index('product_id');
        });
    }
    public function down(): void {
        foreach (['deal_products','deals','lost_reasons','pipeline_stages','pipelines'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
