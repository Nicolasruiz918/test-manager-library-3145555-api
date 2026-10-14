<?php
return [
    'jwt_secret' => env('JWT_SECRET', ''),
    'cover_directory' => env('COVER_DIRECTORY', storage_path('covers')),
    'frontend_origin' => env('FRONTEND_ORIGIN', 'http://localhost:5173'),
];
