<?php
// Included only by the protected backup/restore preflight, with its disposable connection.
if (PHP_SAPI !== 'cli' || !isset($schema, $admin, $source, $before, $result, $isolatedCredentials)
    || !preg_match('/^fc_roster_restore_\d{8}_\d{6}$/D', $schema)
    || $admin->query('SELECT DATABASE()')->fetchColumn() !== $schema) {
    throw new RuntimeException('Disposable restore context required.');
}
$phase = 'copy_migration';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $schema,
    'database.connections.mysql.username' => $isolatedCredentials['user'],
    'database.connections.mysql.password' => $isolatedCredentials['password'],
    'session.driver' => 'array', 'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync',
    'app.url' => 'http://127.0.0.1:8000', 'presentation.origin' => '']);
unset($isolatedCredentials);
Illuminate\Support\Facades\DB::purge('mysql');
if (Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() db')->db !== $schema) throw new RuntimeException('Copy configuration mismatch');
Illuminate\Support\Facades\Mail::fake();
Illuminate\Support\Facades\Notification::fake();
$migration = 'database/migrations/2026_11_24_000000_add_academic_roster_provisioning.php';
if (Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => $migration, '--force' => true]) !== 0) throw new RuntimeException('Copy migration failed');
if ($snapshot($admin, $stableTables) !== $before) {
    // Only the migration ledger changes when the schema is migrated.
    $migrated = $snapshot($admin, array_values(array_diff($stableTables, ['migrations'])));
    $expected = array_diff_key($before, ['migrations' => true]);
    if ($migrated !== $expected) throw new RuntimeException('Migration altered original row data');
}
$phase = 'copy_provisioning';
$manifest = json_decode(file_get_contents(storage_path('app/private/academic-roster-review.json')), true, 32, JSON_THROW_ON_ERROR);
$service = app(App\Services\AcademicRosterProvisioner::class);
$review = $service->review($manifest);
$originalIds = $admin->query('SELECT id FROM users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$actor = App\Models\User::where('role', 'admin')->where('status', 'active')->whereNotNull('email_verified_at')
    ->whereNotNull('two_factor_confirmed_at')->where('must_change_password', false)->firstOrFail();
$credentials = [];
$copyResult = $service->provision($manifest, $actor, function ($rows) use (&$credentials) { $credentials = $rows; });
$again = $service->provision($manifest, $actor, fn () => throw new RuntimeException('Repeat import attempted credential creation'));
if ($again['created'] !== 0 || $copyResult['created'] + $copyResult['matched_existing'] !== 41) throw new RuntimeException('Idempotency or count failure');
if (count(array_unique(array_column($credentials, 'temporary_password'))) !== count($credentials)) throw new RuntimeException('Duplicate temporary password');
$existingRows = $admin->query('SELECT * FROM users WHERE id IN ('.implode(',', array_map('intval', $originalIds)).')')->fetchAll(PDO::FETCH_ASSOC);
$encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $existingRows); sort($encoded, SORT_STRING);
if (hash('sha256', json_encode($encoded, JSON_THROW_ON_ERROR)) !== $before['users']['sha256']) throw new RuntimeException('Original copy users changed');
$unchangedTables = array_values(array_diff($stableTables, ['migrations', 'users', 'audit_logs']));
if ($snapshot($admin, $unchangedTables) !== array_intersect_key($before, array_flip($unchangedTables))) throw new RuntimeException('Copy financial/history rows changed');
$phase = 'copy_login_verification';
$session = app('session')->driver();
$session->start();
$verified = 0;
$secretDigests = [];
foreach ($credentials as $credential) {
    $session->flush();
    Illuminate\Support\Facades\Auth::forgetGuards();
    $request = Illuminate\Http\Request::create('http://127.0.0.1:8000/login', 'POST', ['login' => $credential['login_id'], 'password' => $credential['temporary_password']]);
    $request->setLaravelSession($session);
    $app->instance('request', $request);
    $response = app(App\Http\Controllers\AuthController::class)->login($request);
    $user = App\Models\User::where('faculty_id', $credential['login_id'])->firstOrFail();
    if (parse_url($response->getTargetUrl(), PHP_URL_PATH) !== '/onboarding/password'
        || Illuminate\Support\Facades\Auth::id() !== $user->id || !$user->isAcademicRosterDemo()
        || !Illuminate\Support\Facades\Hash::check($credential['temporary_password'], $user->password)
        || $user->email !== null || $user->email_verified_at !== null || $user->two_factor_confirmed_at !== null
        || $user->role !== 'faculty' || !$user->must_change_password) throw new RuntimeException('Disposable first-login verification failed');
    $twoFactor = app(App\Services\TwoFactorService::class);
    $secret = $twoFactor->generateSecret();
    $secretDigests[] = hash('sha256', $secret);
    // Test URI creation without persisting or pre-enrolling any authenticator secret.
    $user->two_factor_secret = $secret;
    if (!str_contains(urldecode($twoFactor->provisioningUri($user)), $user->faculty_id)) throw new RuntimeException('TOTP identity missing');
    $verified++;
}
unset($credential, $credentials, $secret);
if (count(array_unique($secretDigests)) !== $verified) throw new RuntimeException('TOTP uniqueness failed');
Illuminate\Support\Facades\Mail::assertNothingSent();
Illuminate\Support\Facades\Notification::assertNothingSent();
if ($snapshot($source, $stableTables) !== $before) throw new RuntimeException('Source changed during copy verification; review concurrent activity');
$result['roster'] = $copyResult;
$result['reconciliation'] = $review;
$result['copy_login_password_redirects_verified'] = $verified;
$result['repeat_import_created'] = $again['created'];
$result['copy_existing_accounts_unchanged'] = true;
$result['copy_financial_history_unchanged'] = true;
$result['external_messages_sent'] = 0;
$result['normal_migration_applied'] = false;
$result['normal_accounts_inserted'] = 0;
$result['migration_sha256'] = hash_file('sha256', base_path($migration));
