<?php

// Read-only comparison against the user-approved roster, schema and isolated restore.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('display_errors', '0');
$phase = 'identity';
try {
    $directory = 'C:/Users/Charles/FacultyConnect-Recovery/recurring-backups/academic-roster-preflight-20261124-134109';
    $evidence = json_decode(file_get_contents($directory.'/backup-restoration-verified.json'), true, 32, JSON_THROW_ON_ERROR);
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $identity = $pdo->query('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery, @@read_only readonly_mode')->fetch(PDO::FETCH_ASSOC);
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1'
        || $identity['db'] !== 'ccis_local_20261018_164245' || (int)$identity['port'] !== 3307
        || (int)$identity['recovery'] !== 0 || (int)$identity['readonly_mode'] !== 0) throw new RuntimeException('Database identity or health differs from approval');
    $phase = 'reviewed_backup';
    if (!$evidence['source_unchanged'] || $evidence['tables_restored_and_checked'] !== 24
        || $evidence['roster']['created'] !== 40 || $evidence['roster']['matched_existing'] !== 1
        || $evidence['copy_login_password_redirects_verified'] !== 40 || $evidence['repeat_import_created'] !== 0
        || hash_file('sha256', $directory.'/main-before.sql') !== $evidence['backup_sha256']) throw new RuntimeException('Reviewed backup/restore evidence does not verify');
    $migration = 'database/migrations/2026_11_24_000000_add_academic_roster_provisioning.php';
    if (hash_file('sha256', base_path($migration)) !== $evidence['migration_sha256']) throw new RuntimeException('Reviewed migration changed');
    $phase = 'reconciliation';
    $roster = json_decode(file_get_contents(storage_path('app/private/academic-roster-review.json')), true, 32, JSON_THROW_ON_ERROR);
    if ($roster['source_sha256'] !== 'eb266cf1e20f1a749791d1404e4032f3aa3d259a3ecae706972c3d7a7ed1a355') throw new RuntimeException('Source checksum differs');
    $review = app(App\Services\AcademicRosterProvisioner::class)->review($roster);
    if ($review !== $evidence['reconciliation']) throw new RuntimeException('Normal reconciliation differs from reviewed disposable result');
    $phase = 'schema';
    $sql = file_get_contents($directory.'/main-before.sql');
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) !== 24 || Illuminate\Support\Facades\Schema::hasTable('academic_roster_accounts')
        || Illuminate\Support\Facades\DB::table('migrations')->where('migration', basename($migration, '.php'))->exists()) throw new RuntimeException('Production schema/migration state differs');
    $normalize = fn ($value) => preg_replace('/\s+/', ' ', preg_replace('/ AUTO_INCREMENT=\d+/', '', trim($value, " \r\n\t;")));
    foreach ($tables as $table) {
        if (!preg_match('/^CREATE TABLE `'.preg_quote($table, '/').'` \([\s\S]*?^\) [^\r\n]+;/m', $sql, $match)) throw new RuntimeException('Backup table schema missing');
        $create = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
        if ($normalize($create) !== $normalize($match[0])) throw new RuntimeException('Schema differs for '.$table);
        foreach ($pdo->query('CHECK TABLE `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC) as $check) {
            if ($check['Msg_type'] === 'error' || ($check['Msg_type'] === 'status' && $check['Msg_text'] !== 'OK')) throw new RuntimeException('Table health check failed');
        }
    }
    echo json_encode(['identity_verified' => true, 'database' => $identity['db'], 'port' => 3307, 'forced_recovery' => 0,
        'read_only' => false, 'reviewed_backup_and_restore_verified' => true, 'schema_tables_matched' => count($tables),
        'reconciliation_matches_review' => true, 'preserve_user_id' => 45, 'proposed_creates' => 40,
        'migration_pending' => true, 'normal_writes_performed' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['stopped' => true, 'phase' => $phase, 'reason' => $error instanceof PDOException || $error instanceof Illuminate\Database\QueryException ? 'Database read failed; no deployment performed.' : $error->getMessage()]).PHP_EOL);
    exit(1);
}
