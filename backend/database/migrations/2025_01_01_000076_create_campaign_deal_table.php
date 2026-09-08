<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-campaign membership for deals (campaign influence) — the deal-side mirror of
 * campaign_lead / campaign_contact / campaign_customer. A deal can be linked to many
 * campaigns that influenced it, each with its own status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_deal', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $t->string('status', 32)->default('member');   // member | contacted | responded
            $t->timestamp('added_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'deal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_deal');
    }
};
