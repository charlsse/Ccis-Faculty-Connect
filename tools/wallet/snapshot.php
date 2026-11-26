<?php

use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli') {
    exit;
}
try {
    require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_WALLET_CONFIG'));
    $c = config('database.connections.mysql');
    $file = $root.'/wallet-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sql';
    $env = getenv();
    $env['MYSQL_PWD'] = $c['password'];
    $p = proc_open(['C:/xampp/mysql/bin/mysqldump.exe', '--no-defaults', '--host=127.0.0.1', '--port=3307', '--user='.$c['username'], '--single-transaction', '--skip-lock-tables', '--skip-triggers', '--default-character-set=utf8mb4', $c['database']], [0 => ['pipe', 'r'], 1 => ['file', $file, 'x'], 2 => ['pipe', 'w']], $pipes, null, $env);
    fclose($pipes[0]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit = proc_close($p);
    $sql = file_get_contents($file);
    $ok = $exit === 0 && str_contains($sql, '-- Dump completed on') && substr_count($sql, 'CREATE TABLE') === count(DB::select('SHOW TABLES'));
    $result = ['file' => basename($file), 'exit' => $exit, 'complete' => $ok, 'tables' => substr_count($sql, 'CREATE TABLE'), 'sha256' => hash_file('sha256', $file), 'restore_tested' => false, 'excludes' => 'triggers, events, routines'];
    file_put_contents($file.'.json', json_encode($result, JSON_PRETTY_PRINT));
    echo json_encode($result)."\n";
    if (! $ok) {
        exit(1);
    }
} catch (Throwable $e) {
    echo "Synthetic snapshot failed; no credentials emitted.\n";
    exit(1);
}
