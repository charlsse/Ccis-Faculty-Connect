<?php

use App\Services\Wallet\SimulationBoundary;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Used only by the explicit synthetic CLI/router, never normal public/index.php.
$project = dirname(__DIR__, 2);
$private = getenv('FC_WALLET_CONFIG');
if (! is_string($private) || ! is_file($private)) {
    throw new RuntimeException('Private sandbox configuration required.');
}
$root = dirname(realpath($private));
if (str_starts_with(strtolower(str_replace('\\', '/', $root)), strtolower(str_replace('\\', '/', $project)))) {
    throw new RuntimeException('Sandbox configuration must be outside the project.');
}
require_once $project.'/packages/autoload.php';
foreach (['APP_CONFIG_CACHE' => 'config.php', 'APP_ROUTES_CACHE' => 'routes.php', 'APP_SERVICES_CACHE' => 'services.php', 'APP_PACKAGES_CACHE' => 'packages.php'] as $key => $name) {
    putenv($key.'='.$root.'/cache/'.$name);
    $_ENV[$key] = $_SERVER[$key] = $root.'/cache/'.$name;
}
$app = require $project.'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->useEnvironmentPath($root)->loadEnvironmentFrom(basename($private));
$app->useStoragePath($root.'/storage');
$app->make(Kernel::class)->bootstrap();
$db = config('database.connections.mysql.database');
if (config('database.default') !== 'mysql' || ! preg_match('/^ccis_wallet_test_[0-9_]+$/D', (string) $db)
    || config('database.connections.mysql.host') !== '127.0.0.1' || (int) config('database.connections.mysql.port') !== 3307
    || config('app.debug') !== false) {
    throw new RuntimeException('Sandbox configuration identity rejected.');
}
$identity = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
if ($identity->db !== $db || (int) $identity->port !== 3307 || (int) $identity->recovery !== 0) {
    throw new RuntimeException('Sandbox server identity rejected.');
}
config(['mail.default' => 'array', 'water.email_enabled' => false, 'queue.default' => 'sync',
    'cache.default' => 'file', 'cache.stores.file.path' => $root.'/storage/framework/cache/data',
    'view.compiled' => $root.'/storage/framework/views', 'session.files' => $root.'/storage/framework/sessions',
    'filesystems.disks.local.root' => $root.'/storage/app/private', 'filesystems.disks.public.root' => $root.'/storage/app/public',
    'logging.default' => 'single', 'logging.channels.single.path' => $root.'/storage/logs/laravel.log']);
Http::preventStrayRequests();
if (config('payment_gateway.enabled') || config('tunnel.enabled') || env('FC_SANDBOX_REMOTE_APPROVED', false)) {
    throw new RuntimeException('Wallet simulation cannot enable payment or tunnel capabilities.');
}
config(['wallet.enabled' => true]);
SimulationBoundary::check();

return $app;
