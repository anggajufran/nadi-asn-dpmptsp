<?php
return [
    'defaults' => ['guard' => 'web', 'passwords' => 'users'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'users']],
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Modules\IdentityAccess\Models\User::class,
        ],
    ],
    'password_timeout' => 900,
];
