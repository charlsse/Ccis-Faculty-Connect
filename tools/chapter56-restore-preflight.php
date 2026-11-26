<?php
// CLI-only, read-only recovery preflight. Never prints credentials or row contents.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
try {
    $root = 'C:/Users/Charles/FacultyConnect-Recovery';
    $secret = json_decode(file_get_contents($root.'/20261012/isolated-credentials.json'), true, 8, JSON_THROW_ON_ERROR);
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $secret['user'], $secret['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    unset($secret);
    $identity = $pdo->query('SELECT @@port port, @@datadir data_directory, @@version version, @@innodb_force_recovery recovery_mode')->fetch(PDO::FETCH_ASSOC);
    if ((int)$identity['port'] !== 3307 || (int)$identity['recovery_mode'] !== 0) throw new RuntimeException('Unexpected instance');
    $exists = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN ('RESTORED_CCIS_FCLTY_CNNCT','ccis_local_20261018_164245')")->fetchAll(PDO::FETCH_COLUMN);
    $files = [];
    foreach (glob($root.'/recurring-backups/*.sql') as $file) {
        $files[] = ['path'=>$file, 'bytes'=>filesize($file), 'modified'=>date(DATE_ATOM,filemtime($file))];
    }
    usort($files, fn($a,$b)=>strcmp($b['modified'],$a['modified']));
    echo json_encode(['identity'=>$identity, 'schemas'=>$exists, 'recent_backups'=>array_slice($files,0,8)], JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR, 'Preflight stopped: '.get_class($e).'; no database changes made.'.PHP_EOL); exit(1); }
