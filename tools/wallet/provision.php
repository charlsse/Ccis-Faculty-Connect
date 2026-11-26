<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2).'/packages/autoload.php';
try {
    $root = $argv[1] ?? '';
    if (! is_dir($root) || file_exists($root.'/runtime.env')) {
        throw new RuntimeException('New protected directory required.');
    }
    $credentials = json_decode(file_get_contents('C:/Users/Charles/FacultyConnect-Recovery/20261012/isolated-credentials.json'), true, 512, JSON_THROW_ON_ERROR);
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $credentials['user'], $credentials['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ((int) $pdo->query('SELECT @@port')->fetchColumn() !== 3307 || (int) $pdo->query('SELECT @@innodb_force_recovery')->fetchColumn() !== 0) {
        throw new RuntimeException('Server identity rejected.');
    }
    $stamp = date('Ymd_His');
    $db = 'ccis_wallet_test_'.$stamp;
    $runtime = 'fc_wt_'.date('mdHis');
    $migration = 'fc_wm_'.date('mdHis');
    $password = bin2hex(random_bytes(24));
    $migrationPassword = bin2hex(random_bytes(24));
    $pdo->exec('CREATE DATABASE `'.$db.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $scope = str_replace('_', '\\_', $db);
    foreach ([[$runtime, $password, 'SELECT, INSERT, UPDATE, DELETE'], [$migration, $migrationPassword, 'SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES']] as [$user,$pass,$grants]) {
        $pdo->exec('CREATE USER '.$pdo->quote($user)."@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($pass));
        $pdo->exec('GRANT '.$grants.' ON `'.$scope.'`.* TO '.$pdo->quote($user)."@'127.0.0.1'");
    }
    foreach (['cache', 'storage/framework/cache/data', 'storage/framework/views', 'storage/framework/sessions', 'storage/logs', 'storage/app/private', 'storage/app/public'] as $path) {
        if (! is_dir($root.'/'.$path)) {
            mkdir($root.'/'.$path, 0700, true);
        }
    }
    $env = ['APP_NAME' => 'FacultyConnect-Wallet-Simulation', 'APP_ENV' => 'local', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_DEBUG' => 'false',
        'APP_URL' => 'https://localhost:8444', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307', 'DB_DATABASE' => $db, 'DB_USERNAME' => $runtime, 'DB_PASSWORD' => $password,
        'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'facultyconnect_xendit_test_'.$stamp, 'SESSION_SECURE_COOKIE' => 'true', 'SESSION_HTTP_ONLY' => 'true', 'SESSION_SAME_SITE' => 'lax', 'SESSION_DOMAIN' => 'null',
        'MAIL_MAILER' => 'array', 'CACHE_STORE' => 'file', 'PAYMENT_GATEWAY_ENABLED' => 'false', 'PAYMENT_GATEWAY_PROVIDER' => 'xendit', 'PAYMENT_GATEWAY_ENV' => 'sandbox', 'PAYMENT_GATEWAY_SANDBOX_DATA_CONFIRMED' => 'true',
        'WALLET_SIMULATION_ENABLED' => 'true', 'XENDIT_SECRET_KEY' => '', 'XENDIT_CALLBACK_TOKEN' => '', 'XENDIT_BUSINESS_ID' => '', 'XENDIT_TREASURER_ACCOUNT_CONFIRMED' => 'false',
        'TUNNEL_ENABLED' => 'false', 'TUNNEL_HOST' => '', 'FC_SANDBOX_REMOTE_APPROVED' => 'false', 'FC_LOCAL_TLS_TOKEN' => bin2hex(random_bytes(32))];
    $encode = fn ($values) => implode("\n", array_map(fn ($k) => $k.'="'.$values[$k].'"', array_keys($values)))."\n";
    file_put_contents($root.'/runtime.env', $encode($env));
    $env['DB_USERNAME'] = $migration;
    $env['DB_PASSWORD'] = $migrationPassword;
    file_put_contents($root.'/migration.env', $encode($env));
    $metadata = ['database' => $db, 'port' => 3307, 'runtime_account' => $runtime, 'migration_account' => $migration, 'created_utc' => gmdate(DATE_ATOM), 'source_data_copied' => false];
    file_put_contents($root.'/identity.json', json_encode($metadata, JSON_PRETTY_PRINT));
    // Logical hashes only: no source rows or credentials are copied into the synthetic database.
    $preserved = [];
    foreach (['ccis_local_20261018_164245', 'ccis_connect_verified', 'ccis_candidate_20261018'] as $schema) {
        $tables = $pdo->query('SHOW TABLES FROM `'.$schema.'`')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            if ($table === 'sessions') {
                continue;
            }$rows = $pdo->query('SELECT * FROM `'.$schema.'`.`'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $hashes = array_map(fn ($row) => hash('sha256', json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $rows);
            sort($hashes);
            $preserved[$schema][$table] = ['count' => count($rows), 'hash' => hash('sha256', implode("\n", $hashes))];
        }
    }
    file_put_contents($root.'/preservation-before.json', json_encode($preserved, JSON_PRETTY_PRINT));
    file_put_contents($root.'/normal-env.sha256', hash_file('sha256', dirname(__DIR__, 2).'/.env'));
    echo json_encode($metadata).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,"Synthetic provisioning failed; preserve partial artifacts and inspect privately.\n");
    exit(1);
}
