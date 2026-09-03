<?php
return [
    'name' => env('APP_NAME', 'Krama CRM'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [],
    'maintenance' => ['driver' => env('APP_MAINTENANCE_DRIVER', 'file')],

    // Base domain a tenant subdomain is resolved against, e.g. Host "acme.<tenant_domain>" ->
    // subdomain "acme". Read-only/advisory only (TenantResolver) — see ARCHITECTURE.md.
    'tenant_domain' => env('APP_DOMAIN', 'localhost'),
];
