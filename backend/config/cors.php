<?php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],

    // Explicit production origins: whatever FRONTEND_URL / APP_URL resolve to.
    // In production the SPA is same-origin (nginx serves it), so CORS is rarely
    // exercised at all — but we still pin to the real origin(s) rather than any LAN host.
    'allowed_origins' => array_values(array_filter(array_unique([
        env('FRONTEND_URL'),
        env('APP_URL'),
    ]))),

    // Dev convenience only: localhost / 127.0.0.1 / private LAN ranges on any port,
    // enabled ONLY when CORS_ALLOW_LAN=true (default off). Never on in production.
    'allowed_origins_patterns' => env('CORS_ALLOW_LAN', false) ? [
        '/^https?:\/\/localhost(:\d+)?$/',
        '/^https?:\/\/127\.0\.0\.1(:\d+)?$/',
        '/^https?:\/\/192\.168\.\d{1,3}\.\d{1,3}(:\d+)?$/',
        '/^https?:\/\/10\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?$/',
        '/^https?:\/\/172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}(:\d+)?$/',
    ] : [],

    'allowed_headers' => ['*'],
    'exposed_headers' => ['Authorization'],
    'max_age' => 0,
    'supports_credentials' => true,
];
