<?php
return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'admin_username' => env('ADMIN_USERNAME', ''),
    'admin_password' => env('ADMIN_PASSWORD', ''),
    'demo_business_time' => env('DEMO_BUSINESS_TIME', ''),
    'cover_directory' => env('COVERS_DIR', env('COVER_DIRECTORY', storage_path('covers'))),
    'frontend_origin' => env('CORS_ORIGIN', env('FRONTEND_ORIGIN', 'http://localhost:5173')),
];
