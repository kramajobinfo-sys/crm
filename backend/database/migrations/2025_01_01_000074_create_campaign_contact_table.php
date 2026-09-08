<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-campaign membership for contacts — the contact-side mirror of campaign_lead.
 * A contact can belong to many campaigns (marketing lists), each with its own member status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_contact', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $t->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $t->string('status', 32)->default('member');   // member | contacted | responded
            $t->timestamp('added_at')->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_contact');
    }
};
