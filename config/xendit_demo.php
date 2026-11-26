<?php
$private = storage_path('app/private/xendit-demo-config.json');
$settings = env('APP_ENV') !== 'testing' && is_file($private) ? json_decode(file_get_contents($private), true, 16, JSON_THROW_ON_ERROR) : [];
return [
    'enabled' => env('XENDIT_DEMO_ENABLED', $settings['enabled'] ?? false),
    'return_base' => env('XENDIT_DEMO_RETURN_BASE', $settings['return_base'] ?? ''),
    'credentials' => $settings['credentials'] ?? [],
    'main_database' => 'ccis_local_20261018_164245',
];
