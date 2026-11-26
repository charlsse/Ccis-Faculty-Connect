<?php
// Refresh only synthetic accounts in the isolated browser QA database.
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/treasurer-qr-environment.php';
waterBrowserEnvironment();
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = dirname(__DIR__).'/storage/app/private/treasurer-qr-qa';
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $directory.'/database.sqlite') throw new RuntimeException('QA isolation guard failed.');
$accounts = json_decode(file_get_contents($directory.'/accounts.json'), true);
foreach ($accounts as &$account) {
    $user = \App\Models\User::findOrFail($account['id']);
    if (! str_ends_with($user->email, '@water-test.example')) throw new RuntimeException('Not a synthetic account.');
    $account['recovery'] = app(\App\Services\TwoFactorService::class)->generateRecoveryCodes($user)[0];
}
unset($account);
file_put_contents($directory.'/accounts.json', json_encode($accounts));
echo "Synthetic QA recovery codes refreshed.\n";
