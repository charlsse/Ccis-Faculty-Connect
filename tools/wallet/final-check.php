<?php

use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit;
}
try {
    require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_WALLET_CONFIG'));
    $db = DB::connection();
    $db->statement('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    $s = new WalletService;
    $result = ['database' => $db->getDatabaseName(), 'port' => 3307, 'reconciliation' => $s->reconcile(), 'receipts' => $db->table('water_receipts')->count(), 'submissions' => $db->table('water_submissions')->count(), 'withdrawal_states' => $db->table('wallet_withdrawals')->selectRaw('status,COUNT(*) n')->groupBy('status')->pluck('n', 'status')->all(), 'indexes' => 0];
    foreach ($db->select('SHOW TABLES') as $row) {
        $t = array_values((array) $row)[0];
        $expected = $db->table($t)->count();
        $indexes = array_unique(array_map(fn ($i) => $i->Key_name, $db->select('SHOW INDEX FROM `'.$t.'`')));
        foreach ($indexes as $index) {
            $n = count($db->select('SELECT * FROM `'.$t.'` FORCE INDEX (`'.$index.'`)'));
            if ($n !== $expected) {
                throw new RuntimeException('Index scan mismatch.');
            }$result['indexes']++;
        }
    }
    $result['activation_disabled'] = ! config('payment_gateway.enabled') && ! config('tunnel.enabled') && ! env('FC_SANDBOX_REMOTE_APPROVED', false);
    $db->rollBack();
    $cert = openssl_x509_read(file_get_contents($root.'/localhost.crt'));
    $result['certificate_sha256'] = openssl_x509_fingerprint($cert, 'sha256');
    $result['all_passed'] = $result['reconciliation']['matches'] && $result['activation_disabled'] && $result['receipts'] === 3 && $result['submissions'] === 3;
    file_put_contents($root.'/final-check.json', json_encode($result, JSON_PRETTY_PRINT));
    echo json_encode($result)."\n";
    if (! $result['all_passed']) {
        exit(1);
    }
} catch (Throwable $e) {
    echo "Final wallet check failed.\n";
    exit(1);
}
