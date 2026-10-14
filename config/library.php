<?php
return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'admin_username' => env('ADMIN_USERNAME', ''),
    'admin_password' => env('ADMIN_PASSWORD', ''),
    'cover_directory' => env('COVER_DIRECTORY', storage_path('covers')),
    'frontend_origin' => env('FRONTEND_ORIGIN', 'http://localhost:5173'),
];
