<?php
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],

    // Explicit origins (kept for a fixed FRONTEND_URL if you set one)
    'allowed_origins' => array_filter([env('FRONTEND_URL')]),

    // Regex fallbacks: localhost, 127.0.0.1, and any private LAN range
    // (192.168.x.x, 10.x.x.x, 172.16-31.x.x) on any port.
    'allowed_origins_patterns' => [
        '/^https?:\/\/localhost(:\d+)?$/',
        '/^https?:\/\/127\.0\.0\.1(:\d+)?$/',
        '/^https?:\/\/192\.168\.\d{1,3}\.\d{1,3}(:\d+)?$/',
        '/^https?:\/\/10\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?$/',
        '/^https?:\/\/172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}(:\d+)?$/',
    ],

    'allowed_headers' => ['*'],
    'exposed_headers' => ['Authorization'],
    'max_age' => 0,
    'supports_credentials' => true,
];
