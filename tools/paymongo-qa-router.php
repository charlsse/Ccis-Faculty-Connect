<?php

if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
require __DIR__.'/paymongo-qa-environment.php';
waterBrowserEnvironment();
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath($root.'/public/'.$path);
if ($asset && str_starts_with($asset, realpath($root.'/public').DIRECTORY_SEPARATOR) && is_file($asset) && ! str_ends_with($asset, '.php')) {
    return false;
}
define('LARAVEL_START', microtime(true));
require $root.'/packages/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();
// Browser fixture server must never contact a payment provider.
\Illuminate\Support\Facades\Http::preventStrayRequests();
$app->handleRequest(\Illuminate\Http\Request::capture());
