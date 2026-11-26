<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__, 2).'/packages/autoload.php';
try {
    $root = $argv[1] ?? '';
    $project = dirname(__DIR__, 2);
    if (! is_dir($root) || file_exists($root.'/runtime.env')) {
        throw new RuntimeException('Unused protected directory required.');
    }
    $credential = json_decode(file_get_contents('C:/Users/Charles/FacultyConnect-Recovery/20261012/isolated-credentials.json'), true);
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $credential['user'], $credential['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ((int) $pdo->query('SELECT @@port')->fetchColumn() !== 3307 || (int) $pdo->query('SELECT @@innodb_force_recovery')->fetchColumn() !== 0) {
        throw new RuntimeException('Server identity mismatch.');
    }
    $source = 'ccis_local_20261018_164245';
    $stamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Ymd_His');
    $db = 'ccis_unified_review_'.$stamp;
    $hashes = function ($schema) use ($pdo) {
        $out = [];
        foreach ($pdo->query('SHOW TABLES FROM `'.$schema.'`')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if ($table === 'sessions') {
                continue;
            }$rows = $pdo->query('SELECT * FROM `'.$schema.'`.`'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $h = array_map(fn ($r) => hash('sha256', json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $rows);
            sort($h);
            $out[$table] = ['count' => count($rows), 'hash' => hash('sha256', implode("\n", $h))];
        }

return $out;
    };
    $before = $hashes($source);
    file_put_contents($root.'/source-before.json', json_encode($before, JSON_PRETTY_PRINT));
    file_put_contents($root.'/normal-env.sha256', hash_file('sha256', $project.'/.env'));
    $sql = $root.'/source.sql';
    $env = getenv();
    $env['MYSQL_PWD'] = $credential['password'];
    $p = proc_open(['C:/xampp/mysql/bin/mysqldump.exe', '--no-defaults', '--host=127.0.0.1', '--port=3307', '--user='.$credential['user'], '--single-transaction', '--skip-lock-tables', '--skip-add-locks', '--skip-triggers', '--default-character-set=utf8mb4', '--ignore-table='.$source.'.sessions', $source], [0 => ['pipe', 'r'], 1 => ['file', $sql, 'x'], 2 => ['file', $root.'/dump.private.err', 'x']], $pipes, null, $env);
    fclose($pipes[0]);
    if (proc_close($p) !== 0 || ! str_contains(file_get_contents($sql), '-- Dump completed on')) {
        throw new RuntimeException('Source export failed.');
    }
    $pdo->exec('CREATE DATABASE `'.$db.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $scope = str_replace('_', '\\_', $db);
    $runtime = 'fc_ur_'.substr($stamp, 4);
    $migration = 'fc_um_'.substr($stamp, 4);
    $password = bin2hex(random_bytes(24));
    $mp = bin2hex(random_bytes(24));
    foreach ([[$runtime, $password, 'SELECT,INSERT,UPDATE,DELETE'], [$migration, $mp, 'SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES']] as [$u,$pw,$grants]) {
        $pdo->exec('CREATE USER '.$pdo->quote($u)."@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($pw));
        $pdo->exec('GRANT '.$grants.' ON `'.$scope.'`.* TO '.$pdo->quote($u)."@'127.0.0.1'");
    }
    $env['MYSQL_PWD'] = $mp;
    $p = proc_open(['C:/xampp/mysql/bin/mysql.exe', '--no-defaults', '--host=127.0.0.1', '--port=3307', '--user='.$migration, '--default-character-set=utf8mb4', $db], [0 => ['file', $sql, 'r'], 1 => ['file', $root.'/import.private.out', 'x'], 2 => ['file', $root.'/import.private.err', 'x']], $pipes, null, $env);
    if (proc_close($p) !== 0) {
        throw new RuntimeException('Copy import failed.');
    }
    if ($before !== $hashes($db) || $before !== $hashes($source)) {
        throw new RuntimeException('Source or restored copy changed during snapshot; stop.');
    }
    $sessionSql = $pdo->query('SHOW CREATE TABLE `'.$source.'`.`sessions`')->fetch(PDO::FETCH_NUM)[1];
    $pdo->exec('USE `'.$db.'`');
    $pdo->exec($sessionSql);
    foreach (['cache', 'storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'storage/app/private', 'storage/app/public'] as $dir) {
        mkdir($root.'/'.$dir, 0700, true);
    }
    $normal = Dotenv\Dotenv::parse(file_get_contents($project.'/.env'));
    $settings = ['APP_NAME' => 'FacultyConnect Review Copy', 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => $normal['APP_KEY'], 'APP_URL' => 'http://127.0.0.1:8000', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => $db, 'DB_USERNAME' => $runtime, 'DB_PASSWORD' => $password, 'SESSION_DRIVER' => 'array', 'SESSION_COOKIE' => 'fc_unified_review_'.$stamp, 'SESSION_SECURE_COOKIE' => 'true', 'SESSION_HTTP_ONLY' => 'true', 'SESSION_SAME_SITE' => 'lax', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'PAYMENT_GATEWAY_ENABLED' => 'false', 'WALLET_SIMULATION_ENABLED' => 'false', 'TUNNEL_ENABLED' => 'false', 'FC_SANDBOX_REMOTE_APPROVED' => 'false'];
    $encode = fn ($s) => implode("\n", array_map(fn ($k) => $k.'="'.$s[$k].'"', array_keys($s)))."\n";
    file_put_contents($root.'/runtime.env', $encode($settings));
    $settings['DB_USERNAME'] = $migration;
    $settings['DB_PASSWORD'] = $mp;
    file_put_contents($root.'/migration.env', $encode($settings));
    file_put_contents($root.'/identity.json', json_encode(['database' => $db, 'port' => 3307, 'normal_env_unchanged' => hash_equals(trim(file_get_contents($root.'/normal-env.sha256')), hash_file('sha256', $project.'/.env')), 'source_tables' => count($before), 'source_rows' => array_sum(array_column($before, 'count')), 'source_export_sha256' => hash_file('sha256', $sql), 'restored_hashes_match' => true, 'sessions_copied' => false], JSON_PRETTY_PRINT));
    echo file_get_contents($root.'/identity.json');
} catch (Throwable $e) {
    if (isset($root) && is_dir($root)) {
        file_put_contents($root.'/prepare-error.private.txt',$e->getMessage());
    }echo "Preparation failed; inspect protected diagnostics.\n";
    exit(1);
}
