<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website visitor tracking (Zoho gap #12). See docs/VISITS_SCOPE.md.
 *
 * Fed by the app's only PUBLIC write endpoint, so every column here holds attacker-controlled
 * input. Lengths are caps the ingest truncates to rather than limits it rejects on — a
 * truncated page view is more useful than a lost one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // One public site key per tenant. A column rather than a table because one key per
        // tenant is the whole MVP — same shape as companies.subdomain.
        Schema::table('companies', function (Blueprint $t) {
            $t->string('visits_site_key', 40)->nullable()->unique()->after('subdomain');
        });

        Schema::create('web_visitors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Client-generated UUID from localStorage. Validated as a UUID by the ingest, so
            // this index is over a fixed shape and not arbitrary attacker strings.
            $t->string('visitor_uid', 36);

            // Set by identify(); never re-pointed once set (see VISITS_SCOPE).
            $t->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->timestamp('identified_at')->nullable();

            $t->string('first_landing_url', 512)->nullable();
            $t->string('first_referrer', 512)->nullable();
            $t->string('user_agent', 512)->nullable();
            // Salted SHA-256, never the raw address — enough to group requests and spot abuse
            // without the CRM accumulating personal data the tenant did not ask for.
            $t->string('ip_hash', 64)->nullable();

            $t->unsignedInteger('page_view_count')->default(0);
            $t->timestamp('first_seen_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'visitor_uid']);
            $t->index(['company_id', 'lead_id']);
            $t->index(['company_id', 'last_seen_at']);
        });

        Schema::create('web_page_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('visitor_id')->constrained('web_visitors')->cascadeOnDelete();

            $t->string('url', 1024);
            $t->string('path', 512)->nullable();
            $t->string('title', 255)->nullable();
            $t->string('referrer', 512)->nullable();
            $t->timestamp('occurred_at');
            $t->timestamps();

            // Drives both the per-visitor timeline and visits:prune's date sweep.
            $t->index(['company_id', 'occurred_at']);
            $t->index(['visitor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_page_views');
        Schema::dropIfExists('web_visitors');
        Schema::table('companies', function (Blueprint $t) {
            $t->dropUnique(['visits_site_key']);
            $t->dropColumn('visits_site_key');
        });
    }
};
