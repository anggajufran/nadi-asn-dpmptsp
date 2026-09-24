<?php
return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],
        'array' => ['transport' => 'array'],
    ],
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS'),
        'name' => 'NADI ASN · DPMPTSP Prov. Sulawesi Tengah',
    ],
];
