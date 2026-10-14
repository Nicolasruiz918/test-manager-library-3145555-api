<?php

return [
    'name' => 'Manager Library',
    'env' => env('APP_ENV', 'production'),
    'debug' => false,
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => 'UTC',
    'locale' => 'es',
    'fallback_locale' => 'es',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
