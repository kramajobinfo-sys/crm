<?php
namespace App\Services;

use App\Models\Company;

/**
 * Resolves a tenant from an HTTP Host header for branding purposes only — the login/register
 * screens showing the right company name/logo before anyone has authenticated. Deliberately
 * read-only and advisory: nothing in the app authenticates, authorizes, or scopes data against
 * this. A wrong or absent Host (reverse proxy, LAN IP, port-forward — this project supports all
 * three, see docs/LAN_ACCESS.md) must never be able to lock a real login out or leak one tenant's
 * data into another's request — it can only fail to show a logo.
 */
class TenantResolver
{
    public function resolveFromHost(?string $host): ?Company
    {
        if (!$host) return null;
        $host = strtolower($host);
        $base = strtolower((string) config('app.tenant_domain', 'localhost'));
        $suffix = '.'.$base;
        if ($base === '' || !str_ends_with($host, $suffix)) return null;

        $label = substr($host, 0, -strlen($suffix));
        if ($label === '') return null;

        return Company::where('subdomain', $label)->where('is_active', true)->first();
    }
}
