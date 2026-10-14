<?php

return [
    'default' => 'pgsql',
    'connections' => [
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'manager_library'),
            'username' => env('DB_USERNAME', 'library'),
            'password' => env('DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'library,public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
            'timezone' => 'UTC',
        ],
    ],
    // The composition root replaces this repository with schema-comment bookkeeping.
    'migrations' => ['table' => 'migrations', 'update_date_on_publish' => false],
];
