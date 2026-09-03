<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('bc_connections', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 128);
            $t->string('environment', 64)->default('production');   // BC environment name: production | sandbox | custom
            $t->string('tenant_id', 191)->nullable();               // Entra ID (Azure AD) directory id
            $t->string('client_id', 191)->nullable();
            // Nullable on purpose: the documented path is DYNAMICS_CLIENT_SECRET in .env.
            // DB storage exists for multi-tenant setups but means a DB dump + APP_KEY is a
            // full credential compromise, so it is the fallback, not the default.
            $t->text('client_secret')->nullable();
            $t->string('bc_company_id', 64)->nullable();            // GUID of the BC company to bind to
            $t->string('bc_company_name', 191)->nullable();
            $t->string('base_url', 255)->nullable();                // override for region/on-prem
            $t->string('api_version', 16)->default('v2.0');
            $t->boolean('is_active')->default(true);
            $t->string('status', 24)->default('unconfigured');      // unconfigured|ok|auth_failed|unreachable
            $t->timestamp('last_connected_at')->nullable();
            $t->string('last_error', 1000)->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'name']);
        });

        Schema::create('bc_entity_mappings', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('bc_connections')->cascadeOnDelete();
            $t->string('crm_entity', 64);        // e.g. company, customer, product — a key, not an FK:
                                                 // most CRM modules ship later (see ERD 2-15)
            $t->string('bc_entity', 64);         // OData entity set, e.g. companies, customers, salesInvoices
            $t->string('direction', 16)->default('pull');   // pull|push|bidirectional
            $t->boolean('is_enabled')->default(true);
            $t->json('field_map')->nullable();   // crm_field => bc_field
            $t->json('filter')->nullable();      // OData $filter fragments
            $t->timestamp('sync_cursor')->nullable();       // high-water mark on lastModifiedDateTime
            $t->unsignedInteger('interval_minutes')->default(60);
            $t->timestamp('last_run_at')->nullable();
            $t->timestamps();
            $t->unique(['connection_id', 'crm_entity', 'bc_entity'], 'bc_map_unique');
        });

        Schema::create('bc_record_links', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('bc_connections')->cascadeOnDelete();
            $t->foreignId('mapping_id')->nullable()->constrained('bc_entity_mappings')->nullOnDelete();
            $t->string('crm_type', 191);         // model class or entity key
            $t->unsignedBigInteger('crm_id');
            $t->string('bc_entity', 64);         // BC GUIDs are unique per entity set, so the
                                                 // uniqueness below must include the set
            $t->string('bc_id', 64);
            $t->string('bc_etag', 191)->nullable();   // for If-Match optimistic concurrency
            $t->string('payload_hash', 64)->nullable();
            $t->string('last_direction', 16)->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->unique(['connection_id', 'crm_type', 'crm_id'], 'bc_link_crm_unique');
            $t->unique(['connection_id', 'bc_entity', 'bc_id'], 'bc_link_bc_unique');
        });

        Schema::create('bc_sync_runs', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('bc_connections')->cascadeOnDelete();
            $t->foreignId('mapping_id')->nullable()->constrained('bc_entity_mappings')->nullOnDelete();
            $t->string('direction', 16);
            $t->string('trigger', 16)->default('manual');   // manual|scheduled|webhook
            $t->string('status', 16)->default('queued');    // queued|running|success|partial|failed
            $t->unsignedInteger('created_count')->default(0);
            $t->unsignedInteger('updated_count')->default(0);
            $t->unsignedInteger('skipped_count')->default(0);
            $t->unsignedInteger('failed_count')->default(0);
            $t->timestamp('started_at')->nullable(); $t->timestamp('finished_at')->nullable();
            $t->string('error', 1000)->nullable();
            $t->timestamps();
            $t->index(['company_id', 'connection_id', 'created_at']);
        });

        Schema::create('bc_sync_issues', function (Blueprint $t) {
            $t->id(); $t->foreignId('run_id')->constrained('bc_sync_runs')->cascadeOnDelete();
            $t->foreignId('record_link_id')->nullable()->constrained('bc_record_links')->nullOnDelete();
            $t->string('stage', 32);                        // auth|fetch|map|write|conflict
            $t->string('severity', 16)->default('error');   // warning|error|conflict
            $t->string('crm_type', 191)->nullable(); $t->unsignedBigInteger('crm_id')->nullable();
            $t->string('bc_id', 64)->nullable();
            $t->string('message', 1000);
            $t->json('context')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['run_id', 'severity']);
        });

        Schema::create('bc_webhook_subscriptions', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('connection_id')->constrained('bc_connections')->cascadeOnDelete();
            $t->string('resource', 191);                    // BC resource path being watched
            $t->string('subscription_id', 191)->nullable();
            $t->string('notification_url', 500)->nullable();
            $t->text('client_state')->nullable();           // encrypted shared secret for callback validation
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('last_renewed_at')->nullable();
            $t->timestamps();
            $t->index(['connection_id', 'resource']);
        });
    }
    public function down(): void {
        foreach (['bc_webhook_subscriptions','bc_sync_issues','bc_sync_runs',
            'bc_record_links','bc_entity_mappings','bc_connections'] as $tbl) Schema::dropIfExists($tbl);
    }
};
