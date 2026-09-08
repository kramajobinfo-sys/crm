<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-campaign membership for leads. A lead can belong to many campaigns (marketing lists),
 * each with its own member status. This is distinct from leads.campaign_id, which stays as the
 * single SOURCE campaign that generated the lead (attribution/ROI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_lead', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->string('status', 32)->default('member');   // member | contacted | responded
            $t->timestamp('added_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_lead');
    }
};
