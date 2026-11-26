<?php

use App\Services\Wallet\SyntheticFixtures;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

if (PHP_SAPI !== 'cli') {
    exit;
}
try {
    $app = require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_WALLET_CONFIG'));
    if (Schema::hasTable('users')) {
        throw new RuntimeException('Fresh schema required; do not rerun.');
    }
    Artisan::call('migrate', ['--path' => 'database/migrations', '--force' => true]);
    Artisan::call('migrate', ['--path' => 'database/experimental/wallet', '--force' => true]);
    $password = bin2hex(random_bytes(16));
    $fixtures = SyntheticFixtures::seed($password);
    file_put_contents($root.'/test-accounts.private.json', json_encode(['password' => $password, 'totp_secret' => 'JBSWY3DPEHPK3PXP', 'accounts' => ['treasurer@wallet.invalid', 'admin@wallet.invalid', 'faculty@wallet.invalid']], JSON_PRETTY_PRINT));
    file_put_contents($root.'/fixtures.json', json_encode($fixtures, JSON_PRETTY_PRINT));
    echo "Synthetic-only migrations and fixtures created; credentials retained privately.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Preparation failed; no real schema targeted. Inspect protected diagnostic.\n");
    if (isset($root)) {
        file_put_contents($root.'/prepare-error.private.txt', $e->getMessage());
    }exit(1);
}
