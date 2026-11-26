<?php

// Explicitly approved deployment only. No credential values leave memory or change .env.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('display_errors', '0');
$phase = 'approved_backup_guard';
try {
    $directory = $argv[1] ?? '';
    $resolved = realpath($directory);
    $allowedRoot = realpath('C:/Users/Charles/FacultyConnect-Recovery/recurring-backups');
    if (!$resolved || !$allowedRoot || !str_starts_with(strtolower($resolved), strtolower($allowedRoot).DIRECTORY_SEPARATOR)) throw new RuntimeException('Unexpected evidence directory');
    $manifest = json_decode(file_get_contents($resolved.'/backup-restoration-verified.json'), true, 32, JSON_THROW_ON_ERROR);
    if (!$manifest['source_unchanged'] || $manifest['tables_restored_and_checked'] !== 24 || $manifest['forced_recovery'] !== 0
        || hash_file('sha256', $resolved.'/main-before.sql') !== $manifest['backup_sha256']) throw new RuntimeException('Verified backup guard failed');
    $mainPath = 'database/migrations/2026_11_20_000000_add_payment_notification_delivery.php';
    $demoPath = 'database/demo/002_create_notification_outbox.php';
    if (hash_file('sha256', base_path($mainPath)) !== $manifest['migration_hashes']['main']
        || hash_file('sha256', base_path($demoPath)) !== $manifest['migration_hashes']['demo']) throw new RuntimeException('Reviewed migration changed after backup verification');
    $identity = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
    if ($identity->db !== 'ccis_local_20261018_164245' || (int)$identity->port !== 3307 || (int)$identity->recovery !== 0
        || config('database.connections.mysql.host') !== '127.0.0.1' || config('water.email_enabled')) throw new RuntimeException('Main identity or disabled-email guard failed');
    if (config('database.connections.xendit_demo.database') !== storage_path('app/private/xendit-demo.sqlite')) throw new RuntimeException('Unexpected demo database');
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $grantsBefore = hash('sha256', json_encode($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM)));
    $configBefore = hash_file('sha256', base_path('.env'));
    $protected = ['users', 'payments', 'water_collections', 'water_members', 'water_submissions', 'water_receipts', 'water_payment_accounts', 'audit_logs', 'water_reference_claims', 'water_settings'];
    $snapshot = function () use ($pdo, $protected) {
        $result = [];
        foreach ($protected as $table) {
            $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
            sort($encoded, SORT_STRING);
            $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($encoded, JSON_THROW_ON_ERROR))];
        }
        return $result;
    };
    $before = $snapshot();
    file_put_contents($resolved.'/deployment-before.json', json_encode($before, JSON_PRETTY_PRINT));
    $demoPdo = Illuminate\Support\Facades\DB::connection('xendit_demo')->getPdo();
    $demoPdo->exec('VACUUM INTO '.$demoPdo->quote($resolved.'/demo-before.sqlite'));
    $demoBefore = $demoPdo->query('SELECT * FROM demo_attempts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $phase = 'main_approved_migration';
    $secret = json_decode(file_get_contents('C:/Users/Charles/FacultyConnect-Recovery/20261012/isolated-credentials.json'), true, 8, JSON_THROW_ON_ERROR);
    $schemaConnection = config('database.connections.mysql');
    $schemaConnection['username'] = $secret['user'];
    $schemaConnection['password'] = $secret['password'];
    config(['database.connections.approved_notification_schema' => $schemaConnection]);
    unset($secret, $schemaConnection);
    $exit = Illuminate\Support\Facades\Artisan::call('migrate', ['--database' => 'approved_notification_schema', '--path' => $mainPath, '--force' => true]);
    // Immediately discard the temporary privileged connection/config; ordinary application credentials remain untouched.
    Illuminate\Support\Facades\DB::purge('approved_notification_schema');
    config(['database.connections.approved_notification_schema' => null]);
    if ($exit !== 0) throw new RuntimeException('Approved main migration failed');
    $phase = 'demo_approved_migration';
    if (!Illuminate\Support\Facades\Schema::connection('xendit_demo')->hasTable('notification_outbox')) (require base_path($demoPath))->up();
    // The explicit SQLite installer uses the existing main repository rather than adding an unreviewed SQLite migrations table.
    $demoMigration = '002_create_notification_outbox';
    if (!Illuminate\Support\Facades\DB::table('migrations')->where('migration', $demoMigration)->exists()) {
        Illuminate\Support\Facades\DB::table('migrations')->insert(['migration' => $demoMigration, 'batch' => Illuminate\Support\Facades\DB::table('migrations')->max('batch')]);
    }
    $phase = 'schema_and_preservation_verification';
    $columns = $pdo->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='water_notices' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    $indexes = $pdo->query("SHOW INDEX FROM water_notices")->fetchAll(PDO::FETCH_ASSOC);
    $demoColumns = $demoPdo->query('PRAGMA table_info(notification_outbox)')->fetchAll(PDO::FETCH_ASSOC);
    $demoIndexes = $demoPdo->query('PRAGMA index_list(notification_outbox)')->fetchAll(PDO::FETCH_ASSOC);
    $names = array_column($columns, 'COLUMN_NAME');
    foreach (['event_key', 'context', 'email_state', 'email_attempts', 'email_next_at', 'email_delivered_at'] as $name) if (!in_array($name, $names, true)) throw new RuntimeException('Missing approved notification column');
    if (!array_filter($indexes, fn ($row) => $row['Column_name'] === 'event_key' && (int)$row['Non_unique'] === 0)) throw new RuntimeException('Missing event uniqueness constraint');
    if (!array_filter($indexes, fn ($row) => $row['Column_name'] === 'email_state')) throw new RuntimeException('Missing delivery index');
    if (!array_filter($demoColumns, fn ($row) => $row['name'] === 'event_key' && (int)$row['pk'] === 1)) throw new RuntimeException('Missing demo primary key');
    if (!array_filter($demoIndexes, fn ($row) => $row['name'] === 'notification_outbox_delivered_at_index')) throw new RuntimeException('Missing demo delivery index');
    $after = $snapshot();
    if ($before !== $after || $demoBefore !== $demoPdo->query('SELECT * FROM demo_attempts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)) throw new RuntimeException('Protected account, financial or demo attempt data changed');
    if ($grantsBefore !== hash('sha256', json_encode($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM))) || $configBefore !== hash_file('sha256', base_path('.env'))) throw new RuntimeException('Application grants or configuration changed');
    $records = Illuminate\Support\Facades\DB::table('migrations')->whereIn('migration', ['2026_11_20_000000_add_payment_notification_delivery', $demoMigration])->get();
    if ($records->count() !== 2) throw new RuntimeException('Migration records incomplete');
    $result = ['deployed' => true, 'email_disabled' => true, 'protected_main_tables_unchanged' => count($protected), 'demo_attempts_unchanged' => true,
        'application_grants_unchanged' => true, 'environment_file_unchanged' => true, 'migration_records' => $records,
        'main_columns' => $columns, 'main_indexes' => $indexes, 'demo_columns' => $demoColumns, 'demo_indexes' => $demoIndexes, 'before' => $before, 'after' => $after];
    file_put_contents($resolved.'/deployment-verified.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['deployed' => true, 'migration_records' => 2, 'protected_tables_unchanged' => count($protected), 'demo_attempts_unchanged' => true, 'grants_and_env_unchanged' => true, 'email_disabled' => true]).PHP_EOL;
} catch (Throwable $error) {
    Illuminate\Support\Facades\DB::purge('approved_notification_schema');
    config(['database.connections.approved_notification_schema' => null]);
    fwrite(STDERR, json_encode(['stopped' => true, 'phase' => $phase, 'exception_type' => get_class($error), 'reason' => $error instanceof Illuminate\Database\QueryException || $error instanceof PDOException ? 'Database schema operation failed; inspect approved deployment state.' : $error->getMessage()]).PHP_EOL);
    exit(1);
}
