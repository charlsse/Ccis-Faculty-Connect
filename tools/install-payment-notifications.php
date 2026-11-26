<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CLI only; deploys the two explicitly reviewed additive notification migrations.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$identity = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
if ($identity->db !== 'ccis_local_20261018_164245' || (int) $identity->port !== 3307 || (int) $identity->recovery !== 0) {
    throw new RuntimeException('Unexpected database identity');
}
if (config('database.connections.xendit_demo.database') !== storage_path('app/private/xendit-demo.sqlite')) {
    throw new RuntimeException('Unexpected demo ledger');
}
$directory = storage_path('app/private/payment-stabilization-'.date('Ymd-His'));
mkdir($directory, 0700, true);
$protected = ['users', 'payments', 'water_collections', 'water_members', 'water_submissions', 'water_receipts', 'water_payment_accounts', 'audit_logs'];
$snapshot = function () use ($protected) {
    $result = [];
    foreach ($protected as $table) {
        $rows = DB::table($table)->orderBy('id')->get();
        $result[$table] = ['rows' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())];
    }

    return $result;
};
$before = $snapshot();
file_put_contents($directory.'/before.json', json_encode($before, JSON_PRETTY_PRINT));
// SQLite's VACUUM INTO produces a consistent backup, including committed WAL contents.
$pdo = DB::connection('xendit_demo')->getPdo();
$pdo->exec('VACUUM INTO '.$pdo->quote($directory.'/demo-before.sqlite'));
$migration = 'database/migrations/2026_11_20_000000_add_payment_notification_delivery.php';
$exit = Artisan::call('migrate', ['--path' => $migration, '--force' => true]);
if ($exit !== 0 || ! Schema::hasColumn('water_notices', 'event_key')) {
    throw new RuntimeException('Main notification migration incomplete');
}
if (! Schema::connection('xendit_demo')->hasTable('notification_outbox')) {
    (require database_path('demo/002_create_notification_outbox.php'))->up();
}
$after = $snapshot();
file_put_contents($directory.'/after.json', json_encode($after, JSON_PRETTY_PRINT));
if ($before !== $after) {
    throw new RuntimeException('Protected financial or account snapshot changed; inspect private evidence');
}
echo 'Reviewed notification schema installed. All '.count($protected).' protected table hashes unchanged.'.PHP_EOL;
echo 'Private evidence: '.$directory.PHP_EOL;
