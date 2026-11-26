<?php
// One-off synthetic-only rotation. Never invoke through HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$stage = 'bootstrap';
try {
    require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_SANDBOX_CONFIG'));
    $db = Illuminate\Support\Facades\DB::connection();
    if ($db->getDatabaseName() !== 'ccis_xendit_test_20261018_111928'
        || config('database.connections.mysql.username') !== 'fc_xt_1018111928'
        || config('payment_gateway.enabled') || config('tunnel.enabled') || env('FC_SANDBOX_REMOTE_APPROVED', false)) throw new RuntimeException();
    $users = $db->table('users')->get();
    if ($users->count() !== 3) throw new RuntimeException();
    foreach ($users as $u) if ($u->email !== $u->role.'@example.invalid' || $u->faculty_id !== 'SYNTH-'.strtoupper($u->role)) throw new RuntimeException();
    $adminConfig = json_decode(file_get_contents('C:/Users/Charles/FacultyConnect-Recovery/20261012/isolated-credentials.json'), true, 512, JSON_THROW_ON_ERROR);
    $admin = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', $adminConfig['user'], $adminConfig['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if ((int)$admin->query('SELECT @@port')->fetchColumn() !== 3307 || (int)$admin->query('SELECT @@innodb_force_recovery')->fetchColumn() !== 0) throw new RuntimeException();
    $oldEnv = file_get_contents($root.'/runtime.env');
    $oldMigration = file_get_contents($root.'/migration.env');
    $oldPassword = config('database.connections.mysql.password');
    $oldKey = config('app.key');
    $cipher = config('app.cipher');
    $oldCrypt = new Illuminate\Encryption\Encrypter(base64_decode(substr($oldKey, 7), true), $cipher);
    $newKey = random_bytes(32);
    $newCrypt = new Illuminate\Encryption\Encrypter($newKey, $cipher);
    $newPassword = bin2hex(random_bytes(32));
    $newBridge = bin2hex(random_bytes(32));
    $models = [App\Models\User::class, App\Models\Payment::class, App\Models\WaterMember::class,
        App\Models\WaterGatewayAttempt::class, App\Models\WaterReceipt::class, App\Models\WaterSubmission::class];
    $changes = []; $encryptedCount = 0;
    // Decrypt every encrypted cast before any mutation; preserve its exact serialized payload.
    foreach ($models as $class) {
        $model = new $class; $table = $model->getTable();
        $columns = array_keys(array_filter($model->getCasts(), fn($cast)=>str_starts_with($cast, 'encrypted')));
        foreach ($db->table($table)->get() as $row) {
            $values = [];
            foreach ($columns as $column) if ($row->$column !== null) {
                $plain = $oldCrypt->decrypt($row->$column, false);
                $values[$column] = $newCrypt->encrypt($plain, false);
                if ($newCrypt->decrypt($values[$column], false) !== $plain) throw new RuntimeException();
                $encryptedCount++;
            }
            if ($values) $changes[$table][$row->id] = $values;
        }
    }
    $accounts = [];
    foreach ($users as $u) {
        $password = bin2hex(random_bytes(18));
        $totp = (new PragmaRX\Google2FA\Google2FA)->generateSecretKey();
        $changes['users'][$u->id] = array_merge($changes['users'][$u->id] ?? [], [
            'password'=>Illuminate\Support\Facades\Hash::make($password),
            'two_factor_secret'=>$newCrypt->encrypt($totp, false),
            'two_factor_recovery_codes'=>$newCrypt->encrypt('[]', false),
            'two_factor_last_used_step'=>null, 'remember_token'=>null,
            'security_version'=>(int)$u->security_version + 1,
        ]);
        $accounts[$u->role] = ['id'=>$u->id,'faculty_id'=>$u->faculty_id,'password'=>$password,'totp_secret'=>$totp];
    }
    $replace = function ($text, $key, $value) {
        if (preg_match_all('/^'.preg_quote($key,'/').'=[^\r\n]*/m', $text) !== 1) throw new RuntimeException();
        return preg_replace_callback('/^'.preg_quote($key,'/').'=[^\r\n]*/m', fn()=> $key.'="'.$value.'"', $text);
    };
    $newEnv = $replace($oldEnv, 'APP_KEY', 'base64:'.base64_encode($newKey));
    $newEnv = $replace($newEnv, 'DB_PASSWORD', $newPassword);
    $newEnv = $replace($newEnv, 'FC_LOCAL_TLS_TOKEN', $newBridge);
    $newMigration = $replace($oldMigration, 'APP_KEY', 'base64:'.base64_encode($newKey));
    $newMigration = $replace($newMigration, 'FC_LOCAL_TLS_TOKEN', $newBridge);
    $evidence = $root.'/rotation-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
    if (!mkdir($evidence, 0700)) throw new RuntimeException();
    // Protected recovery material: never print or use to reactivate compromised credentials.
    foreach (['runtime.env','migration.env','synthetic-accounts.private.json'] as $name) if (!copy($root.'/'.$name, $evidence.'/before-'.$name)) throw new RuntimeException();
    $snapshot = [];
    foreach (array_keys($changes) as $table) $snapshot[$table] = $db->table($table)->get()->toArray();
    $snapshot['sessions'] = $db->table('sessions')->get()->toArray();
    file_put_contents($evidence.'/before-rows.private.json', json_encode($snapshot, JSON_THROW_ON_ERROR));
    $stage = 'database transaction';
    $db->beginTransaction();
    foreach ($changes as $table=>$rows) foreach ($rows as $id=>$values) $db->table($table)->where('id',$id)->update($values);
    $invalidated = $db->table('sessions')->delete();
    $stage = 'account password';
    $admin->exec("ALTER USER 'fc_xt_1018111928'@'127.0.0.1' IDENTIFIED BY ".$admin->quote($newPassword));
    $stage = 'private configuration';
    foreach (['runtime.env'=>$newEnv,'migration.env'=>$newMigration,'synthetic-accounts.private.json'=>json_encode($accounts, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)] as $name=>$contents) {
        if (file_put_contents($root.'/'.$name, $contents, LOCK_EX) !== strlen($contents)) throw new RuntimeException();
    }
    $db->commit();
    $stage = 'verify new authentication';
    $newConnection = new PDO('mysql:host=127.0.0.1;port=3307;dbname=ccis_xendit_test_20261018_111928;charset=utf8mb4', 'fc_xt_1018111928', $newPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $oldRejected = false;
    try { new PDO('mysql:host=127.0.0.1;port=3307;dbname=ccis_xendit_test_20261018_111928','fc_xt_1018111928',$oldPassword,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); }
    catch (PDOException $e) { $oldRejected = ($e->errorInfo[1] ?? null) === 1045; }
    if (!$oldRejected) throw new RuntimeException();
    foreach ($changes as $table=>$rows) foreach ($rows as $id=>$values) {
        $statement = $newConnection->prepare('SELECT * FROM `'.$table.'` WHERE id=?'); $statement->execute([$id]); $row=$statement->fetch(PDO::FETCH_ASSOC);
        foreach ($values as $column=>$value) if ((string)$row[$column] !== (string)$value) throw new RuntimeException();
    }
    $result = ['database'=>'ccis_xendit_test_20261018_111928','encrypted_values_reviewed'=>$encryptedCount,
        'synthetic_accounts_regenerated'=>3,'old_sessions_invalidated'=>$invalidated,'old_database_password_rejected'=>$oldRejected,
        'new_database_connection'=>true,'app_key_rotated'=>true,'bridge_token_rotated'=>true,'remote_activation'=>false];
    file_put_contents($evidence.'/result.json', json_encode($result, JSON_PRETTY_PRINT));
    echo json_encode($result).PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db->transactionLevel()) $db->rollBack();
    fwrite(STDERR, 'Rotation stopped at '.$stage.'. Keep synthetic processes stopped and inspect protected recovery artifacts; no secret details emitted.'.PHP_EOL);
    exit(1);
}
