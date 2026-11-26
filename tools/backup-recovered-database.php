<?php

// CLI only. Configuration and output must live outside the web root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$configPath = $argv[1] ?? '';
$lock = null;
try {
    if (! is_file($configPath)) throw new RuntimeException('Missing private backup configuration.');
    $config = Dotenv\Dotenv::parse(file_get_contents($configPath));
    $directory = rtrim($config['BACKUP_DIRECTORY'], '/\\');
    $webRoot = strtolower(str_replace('\\', '/', realpath(dirname(__DIR__))));
    $resolved = strtolower(str_replace('\\', '/', (string) realpath($directory)));
    if (! is_dir($directory) || $resolved === $webRoot || str_starts_with($resolved, $webRoot.'/')) throw new RuntimeException('Unsafe backup location.');
    if ($config['DB_HOST'] !== '127.0.0.1' || $config['DB_PORT'] !== '3307' || $config['DB_DATABASE'] !== 'ccis_local_20261018_164245') throw new RuntimeException('Unexpected backup target.');
    $lock = fopen($directory.'/backup.lock', 'c');
    if (! flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another backup is running.');
    if (disk_free_space($directory) < 1024 * 1024 * 1024) throw new RuntimeException('Less than 1 GiB free; backup refused.');
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname='.$config['DB_DATABASE'].';charset=utf8mb4', $config['DB_USERNAME'], $config['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $pdo->query('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery')->fetch(PDO::FETCH_ASSOC);
    if ($identity['db'] !== $config['DB_DATABASE'] || (int) $identity['port'] !== 3307 || (int) $identity['recovery'] !== 0) throw new RuntimeException('Database identity check failed.');
    $tables = $pdo->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (! $tables || array_filter($tables, fn ($engine) => $engine !== 'InnoDB')) throw new RuntimeException('Snapshot requires only InnoDB base tables.');
    $id = gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
    $partial = $directory.'/'.$id.'.partial.sql';
    $env = getenv(); $env['MYSQL_PWD'] = $config['DB_PASSWORD'];
    $process = proc_open(['C:/xampp/mysql/bin/mysqldump.exe', '--no-defaults', '--host=127.0.0.1', '--port=3307', '--user='.$config['DB_USERNAME'], '--single-transaction', '--quick', '--skip-lock-tables', '--hex-blob', '--triggers', '--events', '--result-file='.$partial, $config['DB_DATABASE']], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    fclose($pipes[0]); stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || trim($error) !== '') throw new RuntimeException('Database dump did not complete cleanly. Partial file retained.');
    $sql = file_get_contents($partial);
    if (! str_contains($sql, '-- Dump completed on ')) throw new RuntimeException('Missing completion marker.');
    preg_match_all('/^CREATE TABLE `([^`]+)`/m', $sql, $matches);
    $actual = $matches[1]; $expected = array_keys($tables); sort($actual); sort($expected);
    if ($actual !== $expected) throw new RuntimeException('Export table coverage mismatch.');
    $final = $directory.'/'.$id.'.sql';
    if (file_exists($final) || ! rename($partial, $final)) throw new RuntimeException('Unable to finalize unique backup.');
    $result = ['completed_utc' => gmdate(DATE_ATOM), 'database' => $identity['db'], 'port' => 3307, 'tables' => count($expected), 'bytes' => filesize($final), 'sha256' => hash_file('sha256', $final), 'file' => basename($final), 'dump_exit' => 0];
    file_put_contents($directory.'/'.$id.'.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    file_put_contents($directory.'/runs.jsonl', json_encode($result).PHP_EOL, FILE_APPEND | LOCK_EX);
    echo json_encode($result).PHP_EOL;
} catch (Throwable $exception) {
    // Never print PDO/driver errors, raw SQL, credentials, or child stderr.
    $failure = ['failed_utc' => gmdate(DATE_ATOM), 'status' => 'failed', 'reason' => $exception instanceof PDOException ? 'Database connection or read failed.' : 'Backup validation failed. Check configuration, storage, and server availability privately.'];
    if (isset($directory) && is_dir($directory)) file_put_contents($directory.'/runs.jsonl', json_encode($failure).PHP_EOL, FILE_APPEND | LOCK_EX);
    fwrite(STDERR, json_encode($failure).PHP_EOL); exit(1);
}
