<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
try {
    $app = require __DIR__.'/bootstrap.php';
    if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) || ($_SERVER['HTTP_HOST'] ?? '') !== 'localhost:8444' || strlen((string) env('FC_LOCAL_TLS_TOKEN')) < 32 || ! hash_equals((string) env('FC_LOCAL_TLS_TOKEN'), (string) ($_SERVER['HTTP_X_FC_SANDBOX_LOCAL_TLS'] ?? ''))) {
        http_response_code(404);
        exit;
    }
    foreach (['HTTP_X_FC_SANDBOX_LOCAL_TLS', 'HTTP_FORWARDED', 'HTTP_X_FORWARDED_HOST', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_PROTO'] as $key) {
        unset($_SERVER[$key]);
    }
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '8444';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (preg_match('#(?:^|/)[.]|^/(?:storage|tools|packages|vendor|_debugbar|_ignition)(?:/|$)#i', $path)) {
        http_response_code(404);
        exit;
    }
    if (str_starts_with($path, '/webhooks/') || str_starts_with($path, '/payments/xendit/')) {
        http_response_code(404);
        exit;
    }
    if (preg_match('#^/(css|js|images)/[a-zA-Z0-9_./-]+\.(css|js|svg|png|jpg|jpeg|webp|ico)$#D', $path)) {
        $public = realpath(dirname(__DIR__, 2).'/public');
        $file = realpath($public.$path);
        if ($file && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $public).'/') && is_file($file)) {
            $mime = ['css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/x-icon'];
            header('Content-Type: '.$mime[pathinfo($file, PATHINFO_EXTENSION)]);
            readfile($file);
            exit;
        }
    }
    $app->handleRequest(Request::capture());
} catch (Throwable $e) {
    http_response_code(503);
    echo 'Wallet simulation unavailable.';
}
