<?php

use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (PHP_SAPI !== 'cli') {
    exit;
}
try {
    require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_WALLET_CONFIG'));
    $f = json_decode(file_get_contents($root.'/fixtures.json'), true);
    $u = User::findOrFail($f['users']['treasurer']);
    $d = DB::table('wallet_destinations')->first();
    $w = (new WalletService)->request($u, $f['collections'][0], $d->id, $d->version, 7000, $argv[1]);
    echo "reserved\n";
} catch (ValidationException $e) {
    echo "rejected\n";
} catch (Throwable $e) {
    echo "error\n";
    exit(1);
}
