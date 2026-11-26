<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    $db = Illuminate\Support\Facades\DB::connection();
    $identity = (array) $db->selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery, @@datadir datadir');
    if ($identity['db'] !== 'ccis_local_20261018_164245' || (int)$identity['port'] !== 3307 || (int)$identity['recovery'] !== 0) throw new RuntimeException();
    $state = ['identity' => $identity, 'email_enabled' => (bool) config('water.email_enabled'), 'tables' => []];
    foreach (['users','payments','water_collections','water_members','water_submissions','water_receipts','water_payment_accounts','water_settings','audit_logs','water_notices','notifications','trusted_devices'] as $table) {
        if (! Illuminate\Support\Facades\Schema::hasTable($table)) continue;
        $rows = array_map(fn ($row) => json_encode((array)$row, JSON_THROW_ON_ERROR), $db->table($table)->get()->all()); sort($rows, SORT_STRING);
        $state['tables'][$table] = ['count' => count($rows), 'hash' => hash('sha256', json_encode($rows))];
    }
    $state['notices_unread'] = $db->table('water_notices')->whereNull('read_at')->count();
    $state['notice_duplicate_events'] = $db->selectOne('SELECT COUNT(*) n FROM (SELECT event_key FROM water_notices WHERE event_key IS NOT NULL GROUP BY event_key HAVING COUNT(*) > 1) duplicates')->n;
    foreach (['xendit_demo_attempts', 'notification_outbox'] as $table) {
        // Discover the source ledger's actual table name rather than modifying it.
        if ($table === 'xendit_demo_attempts') $table = (new App\Models\XenditDemoAttempt)->getTable();
        $rows = array_map(fn ($row) => json_encode((array)$row, JSON_THROW_ON_ERROR), Illuminate\Support\Facades\DB::connection('xendit_demo')->table($table)->get()->all()); sort($rows, SORT_STRING);
        $state['demo'][$table] = ['count' => count($rows), 'hash' => hash('sha256', json_encode($rows))];
    }
    echo json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR, "Read-only background-state audit failed.\n"); exit(1); }
