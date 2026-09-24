<?php
return [
    'default' => env('QUEUE_CONNECTION', 'database'),
    'connections' => [
        'database' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 120,
            'after_commit' => true,
        ],
    ],
    'failed' => ['driver' => 'database-uuids', 'database' => 'pgsql', 'table' => 'failed_jobs'],
];
