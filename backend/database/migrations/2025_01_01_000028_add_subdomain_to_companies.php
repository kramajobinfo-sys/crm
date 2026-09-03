<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (subdomain slice only — see ARCHITECTURE.md "Per-tenant subdomains" for what's
 * deliberately NOT here): gives every company a unique, URL-safe slug so a request's `Host`
 * header can be resolved to a tenant for branding purposes. Read-only/advisory only — nothing
 * authenticates or authorizes against it (see EnsurePlatformAdmin's sibling doc comment style:
 * this middleware-free resolver must never become an auth dependency).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('subdomain', 63)->nullable()->unique()->after('code');
        });

        DB::table('companies')->where('code', 'KRAMA')->update(['subdomain' => 'krama']);
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('subdomain'));
    }
};
