<?php

// Approved, CLI-only preflight. Administrator secrets stay in memory; no application grants/settings change.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
ini_set('display_errors', '0');
$phase = 'preflight';
try {
    $root = 'C:/Users/Charles/FacultyConnect-Recovery';
    $backup = Dotenv\Dotenv::parse(file_get_contents($root.'/backup.env'));
    $secret = json_decode(file_get_contents($root.'/20261012/isolated-credentials.json'), true, 8, JSON_THROW_ON_ERROR);
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true];
    $admin = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $secret['user'], $secret['password'], $options);
    unset($secret);
    $identity = $admin->query('SELECT @@port port, @@innodb_force_recovery recovery, @@read_only readonly_mode')->fetch(PDO::FETCH_ASSOC);
    if ((int)$identity['port'] !== 3307 || (int)$identity['recovery'] !== 0 || (int)$identity['readonly_mode'] !== 0) throw new RuntimeException('Database health guard failed');
    if ($backup['DB_HOST'] !== '127.0.0.1' || $backup['DB_PORT'] !== '3307' || $backup['DB_DATABASE'] !== 'ccis_local_20261018_164245') throw new RuntimeException('Backup identity guard failed');
    $source = new PDO('mysql:host=127.0.0.1;port=3307;dbname=ccis_local_20261018_164245;charset=utf8mb4', $backup['DB_USERNAME'], $backup['DB_PASSWORD'], $options);
    if ($source->query('SELECT DATABASE()')->fetchColumn() !== 'ccis_local_20261018_164245') throw new RuntimeException('Source identity mismatch');
    $directory = rtrim($backup['BACKUP_DIRECTORY'], '/\\').'/notification-deployment-'.date('Ymd-His');
    if (disk_free_space(dirname($directory)) < 64 * 1024 * 1024) throw new RuntimeException('Insufficient deployment backup space');
    if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create protected evidence directory');
    $tables = $source->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $volatile = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];
    $snapshot = function (PDO $connection, array $tables) {
        $result = [];
        foreach ($tables as $table) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) throw new RuntimeException('Invalid table identifier');
            $rows = $connection->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
            sort($encoded, SORT_STRING);
            $result[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($encoded, JSON_THROW_ON_ERROR))];
        }
        return $result;
    };
    $stableTables = array_values(array_diff($tables, $volatile));
    $before = $snapshot($source, $stableTables);
    $phase = 'backup';
    $file = $directory.'/main-before.sql';
    $environment = getenv();
    $environment['MYSQL_PWD'] = $backup['DB_PASSWORD'];
    $command = ['C:/xampp/mysql/bin/mysqldump.exe', '--no-defaults', '--host=127.0.0.1', '--port=3307', '--user='.$backup['DB_USERNAME'], '--default-character-set=utf8mb4', '--single-transaction', '--quick', '--skip-lock-tables', '--routines', '--events', '--triggers', 'ccis_local_20261018_164245'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $file, 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    unset($environment, $backup);
    if (!is_resource($process)) throw new RuntimeException('Backup process failed');
    fclose($pipes[0]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || trim($stderr) !== '') throw new RuntimeException('Backup process reported an error');
    unset($stderr);
    $sql = file_get_contents($file);
    if (!str_contains($sql, '-- Dump completed on ') || preg_match('/^\s*(USE\s|CREATE\s+DATABASE\s)/mi', $sql)) throw new RuntimeException('Unsafe or incomplete SQL export');
    preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches);
    $exported = $matches[1]; sort($exported); $expected = $tables; sort($expected);
    if ($exported !== $expected) throw new RuntimeException('Backup table coverage differs');
    $phase = 'isolated_restore';
    $schema = 'fc_notify_restore_'.date('Ymd_His');
    if ($admin->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='.$admin->quote($schema))->fetchColumn() !== '0') throw new RuntimeException('Restore schema already exists');
    $admin->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $admin->exec('USE `'.$schema.'`');
    $admin->exec($sql);
    unset($sql);
    $restoredTables = $admin->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    if ($restoredTables !== $tables) throw new RuntimeException('Restored table set differs');
    foreach ($tables as $table) {
        foreach ($admin->query('CHECK TABLE `'.$table.'` EXTENDED')->fetchAll(PDO::FETCH_ASSOC) as $check) {
            if ($check['Msg_type'] === 'error' || ($check['Msg_type'] === 'status' && $check['Msg_text'] !== 'OK')) throw new RuntimeException('Restored table health check failed');
        }
    }
    $restored = $snapshot($admin, $stableTables);
    $after = $snapshot($source, $stableTables);
    if ($before !== $restored || $before !== $after) throw new RuntimeException('Stable source/restored row fingerprints differ');
    $result = ['approved_schema_review' => 'docs/payment-notification-schema-review.md', 'main_database' => 'ccis_local_20261018_164245', 'port' => 3307,
        'forced_recovery' => 0, 'backup_sha256' => hash_file('sha256', $file), 'backup_bytes' => filesize($file), 'restore_schema' => $schema,
        'tables_restored_and_checked' => count($tables), 'stable_tables_matched' => count($stableTables), 'source_unchanged' => true,
        'migration_hashes' => [
            'main' => hash_file('sha256', dirname(__DIR__).'/database/migrations/2026_11_20_000000_add_payment_notification_delivery.php'),
            'demo' => hash_file('sha256', dirname(__DIR__).'/database/demo/002_create_notification_outbox.php')],
        'stable_fingerprints' => $before, 'verified_at' => date(DATE_ATOM)];
    file_put_contents($directory.'/backup-restoration-verified.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode(['backup_restore_verified' => true, 'tables' => count($tables), 'stable_tables_matched' => count($stableTables), 'source_unchanged' => true, 'evidence_directory' => $directory]).PHP_EOL;
} catch (Throwable $error) {
    // Never emit a PDO error, SQL body, credential, or raw driver response.
    fwrite(STDERR, json_encode(['stopped' => true, 'phase' => $phase, 'exception_type' => get_class($error), 'reason' => $error instanceof PDOException ? 'Database operation failed; no deployment attempted.' : $error->getMessage()]).PHP_EOL);
    exit(1);
}
