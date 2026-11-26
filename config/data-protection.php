<?php

return [
    /*
    | Laravel's encrypter uses the cipher and key configured in config/app.php.
    | A dedicated lookup key is recommended in production. When omitted, a
    | purpose-specific key is derived from APP_KEY without storing it.
    */
    'lookup_key' => env('FACULTYCONNECT_LOOKUP_KEY'),

    'mysqldump_binary' => env(
        'MYSQLDUMP_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump',
    ),

    'mysql_binary' => env(
        'MYSQL_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'C:\\xampp\\mysql\\bin\\mysql.exe' : 'mysql',
    ),

    'protected_fields' => [
        'users' => [
            'contact_number',
            'bio',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ],
        'payments' => [
            'reference_no',
            'notes',
        ],
    ],
];
