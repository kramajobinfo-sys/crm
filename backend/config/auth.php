<?php
return [
    'defaults' => ['guard' => 'api', 'passwords' => 'users'],
    'guards' => [
        'web'    => ['driver' => 'session', 'provider' => 'users'],
        'api'    => ['driver' => 'jwt', 'provider' => 'users'],
        'portal' => ['driver' => 'jwt', 'provider' => 'contacts'],
    ],
    'providers' => [
        'users'    => ['driver' => 'eloquent', 'model' => App\Models\User::class],
        'contacts' => ['driver' => 'eloquent', 'model' => App\Models\Contact::class],
    ],
    'passwords' => ['users' => ['provider' => 'users', 'table' => 'password_reset_tokens',
        'expire' => 60, 'throttle' => 60]],
    'password_timeout' => 10800,
];
