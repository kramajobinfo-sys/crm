<?php
return [
    'default' => env('QUEUE_CONNECTION', 'redis'),
    'connections' => [
        'sync' => ['driver' => 'sync'],
        'redis' => ['driver' => 'redis', 'connection' => 'default', 'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 180, 'block_for' => null, 'after_commit' => true],
        'database' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90, 'after_commit' => false],
    ],
    'batching' => ['database' => 'mysql', 'table' => 'job_batches'],
    'failed' => ['driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'), 'database' => 'mysql', 'table' => 'failed_jobs'],
];
