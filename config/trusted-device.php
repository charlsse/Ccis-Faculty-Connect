<?php

return [
    'days' => (int) env('TRUSTED_DEVICE_DAYS', 20),
    'cookie' => env('TRUSTED_DEVICE_COOKIE', 'facultyconnect_trusted_device'),
    'path' => env('SESSION_PATH', '/'),
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production'),
    'same_site' => env('SESSION_SAME_SITE', 'lax'),
];
