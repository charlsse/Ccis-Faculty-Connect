<?php
// Refresh synthetic QA recovery codes only; never loads development database settings.
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__.'/payment-redesign-environment.php';
waterBrowserEnvironment();
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = dirname(__DIR__).'/storage/app/private/payment-redesign-qa';
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $directory.'/database.sqlite') { throw new RuntimeException('QA database guard failed.'); }
$accounts = json_decode(file_get_contents($directory.'/accounts.json'), true);
foreach ($accounts as &$entry) { $user = App\Models\User::findOrFail($entry['id']); $entry['recovery'] = app(App\Services\TwoFactorService::class)->generateRecoveryCodes($user)[0]; }
file_put_contents($directory.'/accounts.json', json_encode($accounts));
echo 'Synthetic QA recovery codes refreshed.';
