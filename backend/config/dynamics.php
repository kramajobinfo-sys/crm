<?php

return [
    /*
    | Microsoft Dynamics 365 Business Central.
    |
    | Credentials: the DOCUMENTED path is env vars below. Per-connection values in
    | bc_connections exist for multi-tenant setups, but storing Entra ID secrets in
    | the database means a DB dump plus APP_KEY is a full compromise — prefer env.
    |
    | NOTE: no call in this module has ever succeeded against a live BC tenant.
    | Hosts differ by region and by environment; treat these as defaults to override.
    */
    'tenant_id'     => env('DYNAMICS_TENANT_ID'),
    'client_id'     => env('DYNAMICS_CLIENT_ID'),
    'client_secret' => env('DYNAMICS_CLIENT_SECRET'),
    'environment'   => env('DYNAMICS_ENVIRONMENT', 'production'),

    // Entra ID (Azure AD) OAuth2 client-credentials endpoint. {tenant} is substituted.
    'token_url' => env('DYNAMICS_TOKEN_URL', 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token'),

    // BC resource root. The scope is this host + '/.default' for client-credentials.
    'resource'  => env('DYNAMICS_RESOURCE', 'https://api.businesscentral.dynamics.com'),
    'scope'     => env('DYNAMICS_SCOPE', 'https://api.businesscentral.dynamics.com/.default'),

    // {tenant} and {environment} are substituted at call time.
    'base_url'    => env('DYNAMICS_BASE_URL', 'https://api.businesscentral.dynamics.com/v2.0/{tenant}/{environment}/api/v2.0'),
    'api_version' => env('DYNAMICS_API_VERSION', 'v2.0'),

    'http' => [
        // Short and bounded on purpose: an unreachable tenant must not pin a queue worker.
        'timeout'         => (int) env('DYNAMICS_HTTP_TIMEOUT', 20),
        'connect_timeout' => (int) env('DYNAMICS_HTTP_CONNECT_TIMEOUT', 8),
        'retries'         => (int) env('DYNAMICS_HTTP_RETRIES', 2),
        'retry_delay_ms'  => (int) env('DYNAMICS_HTTP_RETRY_DELAY', 500),
    ],

    // Access tokens are cached below their real lifetime so a token never expires mid-request.
    'token_cache_skew' => (int) env('DYNAMICS_TOKEN_CACHE_SKEW', 120),

    // Queue must be one the worker actually consumes — see docker-compose.yml.
    'queue' => env('DYNAMICS_QUEUE', 'integrations'),

    'page_size' => (int) env('DYNAMICS_PAGE_SIZE', 100),
];
