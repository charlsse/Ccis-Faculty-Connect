<?php

// Standalone loopback QA only. Never loaded by application routes or providers.
function waterBrowserEnvironment(): array
{
    $root = dirname(__DIR__);
    $directory = $root.'/storage/app/private/treasurer-qr-qa';
    if (! is_dir($directory.'/compiled-views')) mkdir($directory.'/compiled-views', 0700, true);
    $settings = json_decode(file_get_contents($directory.'/environment.json'), true, flags: JSON_THROW_ON_ERROR);
    $environment = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => $settings['key'], 'APP_URL' => 'http://127.0.0.1:8097',
        'APP_TIMEZONE' => 'Asia/Manila', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database.sqlite',
        'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'treasurer_qr_qa_session', 'SESSION_SECURE_COOKIE' => 'false', 'VIEW_COMPILED_PATH' => $directory.'/compiled-views',
        'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'WATER_EMAIL_ENABLED' => 'false', 'QUEUE_CONNECTION' => 'sync', 'PAYMENT_GATEWAY_ENABLED' => 'false', 'PAYMONGO_SECRET_KEY' => '', 'PAYMONGO_WEBHOOK_SECRET' => ''];
    foreach ($environment as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    if (is_file($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Clear cached configuration before isolated QA.');
    }

    return $settings;
}
