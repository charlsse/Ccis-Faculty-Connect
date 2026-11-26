<?php

// Standalone loopback QA only. Never loaded by application routes or providers.
function waterBrowserEnvironment(): array
{
    $root = dirname(__DIR__);
    $directory = $root.'/storage/app/private/paymongo-qa';
    $settings = json_decode(file_get_contents($directory.'/environment.json'), true, flags: JSON_THROW_ON_ERROR);
    $environment = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => $settings['key'], 'APP_URL' => 'http://127.0.0.1:8096',
        'APP_TIMEZONE' => 'Asia/Manila', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $directory.'/database.sqlite',
        'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'paymongo_qa_session', 'SESSION_SECURE_COOKIE' => 'false',
        'PAYMENT_GATEWAY_ENABLED' => 'true', 'PAYMENT_GATEWAY_ENV' => 'sandbox', 'PAYMENT_GATEWAY_PROVIDER' => 'paymongo', 'PAYMENT_GATEWAY_SANDBOX_DATA_CONFIRMED' => 'true', 'PAYMONGO_SECRET_KEY' => 'sk_test_browser_fixture_only', 'PAYMONGO_WEBHOOK_SECRET' => 'synthetic_browser_fixture_secret', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'WATER_EMAIL_ENABLED' => 'false', 'QUEUE_CONNECTION' => 'sync'];
    foreach ($environment as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    if (is_file($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Clear cached configuration before isolated QA.');
    }

    return $settings;
}
