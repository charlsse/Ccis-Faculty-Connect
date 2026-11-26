<?php

use App\Models\User;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (PHP_SAPI !== 'cli') {
    exit;
}
try {
    require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_WALLET_CONFIG'));
    $db = DB::connection();
    $s = new WalletService;
    $f = json_decode(file_get_contents($root.'/fixtures.json'), true);
    $u = User::findOrFail($f['users']['treasurer']);
    $results = ['database' => $db->getDatabaseName(), 'port' => 3307, 'checks' => []];
    $assert = function ($key, $ok) use (&$results) {
        $results['checks'][$key] = (bool) $ok;
        if (! $ok) {
            throw new RuntimeException($key);
        }
    };
    $assert('synthetic_users_only', User::count() === 3 && User::where('email', 'not like', '%@wallet.invalid')->count() === 0);
    $before = [];
    foreach (['users', 'water_members', 'water_submissions', 'water_receipts'] as $t) {
        $before[$t] = hash('sha256', json_encode($db->table($t)->orderBy('id')->get()));
    }
    // Independent PHP processes compete for 70.00 from 99.00. The account-row lock serializes reservations.
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $p = proc_open([PHP_BINARY, __DIR__.'/concurrent-worker.php', (string) Str::uuid()], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $children[] = [$p, $pipes];
    }
    $out = [];
    foreach ($children as [$p,$pipes]) {
        $out[] = trim(stream_get_contents($pipes[1]));
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $assert('worker_exit_'.count($out), proc_close($p) === 0 && $err === '');
    }
    sort($out);
    $assert('concurrent_overspend_prevented', $out === ['rejected', 'reserved']);
    $w = $db->table('wallet_withdrawals')->first();
    $s->transition($u, $w->id, 'failed');
    $s->transition($u, $w->id, 'failed');
    $d = $db->table('wallet_destinations')->first();
    $w = $s->request($u, 1, $d->id, $d->version, 5000, (string) Str::uuid());
    foreach (['processing', 'completed', 'completed', 'reversed', 'reversed'] as $o) {
        $s->transition($u, $w->id, $o);
    }
    $assert('ledger_conservation', array_sum($s->balances()) === 18000 && $s->balances()['available'] === 9900 && $s->balances()['reserved'] === 0);
    foreach ($before as $t => $hash) {
        $assert($t.'_unchanged', hash_equals($hash, hash('sha256', json_encode($db->table($t)->orderBy('id')->get()))));
    }
    foreach ($db->select('SHOW TABLES') as $row) {
        $table = array_values((array) $row)[0];
        foreach ($db->select('CHECK TABLE `'.$table.'` EXTENDED') as $check) {
            $assert('table_'.$table, $check->Msg_type === 'status' && $check->Msg_text === 'OK');
        }
    }
    $constraints = $db->select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
    foreach ($constraints as $fk) {
        $sql = 'SELECT COUNT(*) n FROM `'.$fk->TABLE_NAME.'` c LEFT JOIN `'.$fk->REFERENCED_TABLE_NAME.'` p ON c.`'.$fk->COLUMN_NAME.'`=p.`'.$fk->REFERENCED_COLUMN_NAME.'` WHERE c.`'.$fk->COLUMN_NAME.'` IS NOT NULL AND p.`'.$fk->REFERENCED_COLUMN_NAME.'` IS NULL';
        $assert('fk_'.$fk->TABLE_NAME.'_'.$fk->COLUMN_NAME, (int) $db->selectOne($sql)->n === 0);
    }
    $assert('rollback_protects_history', (function () {
        try {
            (require dirname(__DIR__, 2).'/database/experimental/wallet/2026_10_18_230000_create_simulated_wallet.php')->down();

            return false;
        } catch (RuntimeException $e) {
            return str_contains($e->getMessage(), 'history exists');
        }
    })());
    $results['balance'] = $s->balances();
    file_put_contents($root.'/validation.json', json_encode($results, JSON_PRETTY_PRINT));
    echo json_encode(['checks' => count($results['checks']), 'all_passed' => ! in_array(false, $results['checks'], true)])."\n";
} catch (Throwable $e) {
    if (isset($root)) {
        file_put_contents($root.'/validation-error.private.txt',$e->getMessage());
    }echo "Wallet validation failed; inspect protected diagnostics.\n";
    exit(1);
}
