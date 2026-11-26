<?php

// One explicitly authorized local-operator deployment. No HTTP entry point or secret output.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('display_errors', '0');
$phase = 'approval_and_backup_guard';
$reportCreated = false;
$accountsCommitted = false;
$report = 'C:/Users/Charles/FacultyConnect-Recovery/recurring-backups/academic-roster-preflight-20261124-134109/new-faculty-credentials.json';
$temporaryConnection = 'approved_academic_roster_schema';
try {
    if (($argv[2] ?? '') !== '--approved-40-accounts') throw new RuntimeException('Explicit approved deployment argument required');
    $directory = realpath($argv[1] ?? '');
    $allowedRoot = realpath('C:/Users/Charles/FacultyConnect-Recovery/recurring-backups');
    if (!$directory || !$allowedRoot || !str_starts_with(strtolower($directory), strtolower($allowedRoot).DIRECTORY_SEPARATOR)) throw new RuntimeException('Unexpected backup directory');
    $check = new Symfony\Component\Process\Process([PHP_BINARY, __DIR__.'/academic-roster-deployment-preflight.php']);
    $check->mustRun();
    $backup = json_decode(file_get_contents($directory.'/backup-restoration-verified.json'), true, 32, JSON_THROW_ON_ERROR);
    if (!$backup['source_unchanged'] || $backup['main_database'] !== 'ccis_local_20261018_164245'
        || $backup['port'] !== 3307 || $backup['tables_restored_and_checked'] !== 24
        || $backup['roster']['created'] !== 40 || $backup['roster']['matched_existing'] !== 1
        || $backup['copy_login_password_redirects_verified'] !== 40 || $backup['repeat_import_created'] !== 0
        || hash_file('sha256', $directory.'/main-before.sql') !== $backup['backup_sha256']) throw new RuntimeException('Fresh backup/restore does not verify');
    $migrationPath = 'database/migrations/2026_11_24_000000_add_academic_roster_provisioning.php';
    if (hash_file('sha256', base_path($migrationPath)) !== $backup['migration_sha256']) throw new RuntimeException('Reviewed migration checksum changed');
    foreach ([$directory, dirname($report)] as $protectedDirectory) {
        (new Symfony\Component\Process\Process(['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', __DIR__.'/Test-PrivateRosterDirectory.ps1', '-Directory', $protectedDirectory]))->mustRun();
    }
    if (file_exists($report)) throw new RuntimeException('Credential report already exists; refusing to overwrite');
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    $identity = $pdo->query('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery, @@read_only readonly_mode')->fetch(PDO::FETCH_ASSOC);
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1'
        || $identity['db'] !== 'ccis_local_20261018_164245' || (int)$identity['port'] !== 3307
        || (int)$identity['recovery'] !== 0 || (int)$identity['readonly_mode'] !== 0) throw new RuntimeException('Normal database identity or health mismatch');
    $snapshotRows = function (string $table, ?array $ids = null) use ($pdo): array {
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) throw new RuntimeException('Invalid table identifier');
        $where = $ids === null ? '' : ' WHERE id IN ('.implode(',', array_map('intval', $ids)).')';
        $rows = $pdo->query('SELECT * FROM `'.$table.'`'.$where)->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($encoded, SORT_STRING);
        return ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($encoded, JSON_THROW_ON_ERROR))];
    };
    $before = [];
    foreach (array_keys($backup['stable_fingerprints']) as $table) $before[$table] = $snapshotRows($table);
    if ($before !== $backup['stable_fingerprints']) throw new RuntimeException('Live stable data changed since fresh backup; stop and review');
    $protectedFiles = ['.env', 'config/database.php', 'config/payment_gateway.php', 'config/xendit_demo.php',
        'config/presentation.php', 'tools/demo-ngrok.example.yml', 'tools/presentation-origin.cjs',
        'storage/app/private/presentation-origin.json', 'storage/app/private/xendit-demo-config.json'];
    $fileHashes = [];
    foreach ($protectedFiles as $file) if (is_file(base_path($file))) $fileHashes[$file] = hash_file('sha256', base_path($file));
    $grantsBefore = hash('sha256', json_encode($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM)));
    $originalIds = [];
    foreach (['users', 'audit_logs', 'migrations'] as $table) $originalIds[$table] = $pdo->query('SELECT id FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $manifest = json_decode(file_get_contents(storage_path('app/private/academic-roster-review.json')), true, 32, JSON_THROW_ON_ERROR);
    $service = app(App\Services\AcademicRosterProvisioner::class);
    $review = $service->review($manifest);
    if ($review !== $backup['reconciliation'] || count(array_filter($review, fn ($r) => $r['outcome'] === 'create')) !== 40
        || array_values(array_filter($review, fn ($r) => $r['outcome'] === 'preserve_existing'))[0]['existing_user_id'] !== 45) throw new RuntimeException('Reconciliation differs from approved 40 inserts/one preserved account');
    $actors = App\Models\User::where('role', 'admin')->where('status', 'active')->whereNotNull('email_verified_at')
        ->whereNotNull('two_factor_confirmed_at')->where('must_change_password', false)->get();
    if ($actors->count() !== 1) throw new RuntimeException('Expected one existing fully onboarded Administrator for approved operator provenance');
    $actor = $actors->first();
    // The user expressly authorized this local operator deployment. No administrator sign-in is simulated.
    file_put_contents($directory.'/normal-deployment-before.json', json_encode(['identity' => $identity, 'stable_fingerprints' => $before,
        'protected_file_hashes' => $fileHashes, 'original_user_ids' => $originalIds['users'], 'reconciliation' => $review,
        'authorization' => 'User explicitly approved the reviewed migration and exactly 40 missing Faculty accounts.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $phase = 'approved_schema_migration';
    $secret = json_decode(file_get_contents('C:/Users/Charles/FacultyConnect-Recovery/20261012/isolated-credentials.json'), true, 8, JSON_THROW_ON_ERROR);
    $schemaConfig = config('database.connections.mysql');
    $schemaConfig['username'] = $secret['user']; $schemaConfig['password'] = $secret['password'];
    config(['database.connections.'.$temporaryConnection => $schemaConfig]);
    unset($secret, $schemaConfig);
    $exit = Illuminate\Support\Facades\Artisan::call('migrate', ['--database' => $temporaryConnection, '--path' => $migrationPath, '--force' => true]);
    Illuminate\Support\Facades\DB::purge($temporaryConnection);
    config(['database.connections.'.$temporaryConnection => null]);
    if ($exit !== 0 || !Illuminate\Support\Facades\DB::table('migrations')->where('migration', basename($migrationPath, '.php'))->exists()) throw new RuntimeException('Approved migration did not complete');
    if (!Illuminate\Support\Facades\Schema::hasTable('academic_roster_accounts') || Illuminate\Support\Facades\DB::table('academic_roster_accounts')->exists()) throw new RuntimeException('Unexpected roster provenance state after migration');
    $emailColumn = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email'")->fetchColumn();
    $indexes = $pdo->query('SHOW INDEX FROM users')->fetchAll(PDO::FETCH_ASSOC);
    if ($emailColumn !== 'YES' || !array_filter($indexes, fn ($r) => $r['Column_name'] === 'email' && (int)$r['Non_unique'] === 0)) throw new RuntimeException('Nullable email or unique index verification failed');
    file_put_contents($directory.'/normal-migration-applied.json', json_encode(['migration' => basename($migrationPath, '.php'), 'nullable_email' => true, 'email_unique_index_retained' => true, 'completed_at' => now()->toIso8601String()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $phase = 'approved_40_account_transaction';
    // These process-local transports prevent external messages; no saved mail configuration changes.
    Illuminate\Support\Facades\Mail::fake();
    Illuminate\Support\Facades\Notification::fake();
    config(['session.driver' => 'array', 'cache.default' => 'array']);
    $result = $service->provision($manifest, $actor, function (array $credentials) use ($report, &$reportCreated): void {
        if (count($credentials) !== 40 || count(array_unique(array_column($credentials, 'temporary_password'))) !== 40) throw new RuntimeException('Expected 40 unique credential records');
        $stream = fopen($report, 'x');
        if (!$stream) throw new RuntimeException('Exclusive credential report creation failed');
        $reportCreated = true;
        try {
            $json = json_encode(['purpose' => 'Academic demonstration. Generated roster login IDs are not official UMak faculty IDs.',
                'created_at' => now()->toIso8601String(), 'credentials' => $credentials], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (fwrite($stream, $json) !== strlen($json) || !fflush($stream)) throw new RuntimeException('Protected credential report write failed');
        } finally { fclose($stream); }
    });
    $accountsCommitted = true;
    if ($result['created'] !== 40 || $result['matched_existing'] !== 1) throw new RuntimeException('Post-import count discrepancy');
    $phase = 'normal_account_and_login_verification';
    $credentials = json_decode(file_get_contents($report), true, 32, JSON_THROW_ON_ERROR)['credentials'];
    if (count($credentials) !== 40) throw new RuntimeException('Report count mismatch');
    $session = app('session')->driver(); $session->start();
    $newIds = [];
    foreach ($credentials as $credential) {
        $user = App\Models\User::where('faculty_id', $credential['login_id'])->firstOrFail();
        if (in_array((string)$user->id, $originalIds['users'], true) || !$user->isAcademicRosterDemo() || $user->role !== 'faculty'
            || $user->email !== null || $user->email_verified_at !== null || !$user->must_change_password
            || !$user->temporary_password_expires_at?->isFuture() || $user->two_factor_secret !== null
            || $user->two_factor_confirmed_at !== null || $user->two_factor_recovery_codes !== null
            || !Illuminate\Support\Facades\Hash::check($credential['temporary_password'], $user->password)) throw new RuntimeException('New account credential or security state verification failed');
        $newIds[] = $user->id;
        $session->flush(); Illuminate\Support\Facades\Auth::forgetGuards();
        $request = Illuminate\Http\Request::create('http://127.0.0.1:8000/login', 'POST', ['login' => $credential['login_id'], 'password' => $credential['temporary_password']]);
        $request->setLaravelSession($session); $app->instance('request', $request);
        $response = app(App\Http\Controllers\AuthController::class)->login($request);
        if (Illuminate\Support\Facades\Auth::id() !== $user->id || parse_url($response->getTargetUrl(), PHP_URL_PATH) !== '/onboarding/password') throw new RuntimeException('Normal first-login redirect verification failed');
    }
    unset($credentials, $credential);
    $again = $service->provision($manifest, $actor, fn () => throw new RuntimeException('Repeat import attempted credential creation'));
    if ($again['created'] !== 0 || $again['matched_existing'] !== 41) throw new RuntimeException('Idempotency verification failed');
    $phase = 'preservation_and_integrity';
    foreach ($before as $table => $fingerprint) {
        $after = isset($originalIds[$table]) ? $snapshotRows($table, $originalIds[$table]) : $snapshotRows($table);
        if ($after !== $fingerprint) throw new RuntimeException('Pre-existing rows changed in '.$table);
    }
    if (App\Models\User::count() !== $before['users']['rows'] + 40 || Illuminate\Support\Facades\DB::table('academic_roster_accounts')->count() !== 40
        || App\Models\User::whereIn('id', $newIds)->where('status', 'active')->count() !== 40) throw new RuntimeException('Account/directory totals do not reconcile');
    $directoryRequest = Illuminate\Http\Request::create('http://127.0.0.1:8000/dashboard/directory');
    $directoryData = app(App\Http\Controllers\DirectoryController::class)->index($directoryRequest)->getData();
    if ($directoryData['members']->total() !== $result['active_users']) throw new RuntimeException('Directory total differs from active-user total');
    $duplicates = $pdo->query('SELECT COUNT(*) FROM (SELECT faculty_id FROM users GROUP BY faculty_id HAVING COUNT(*) > 1) duplicates')->fetchColumn();
    $duplicateEmails = $pdo->query('SELECT COUNT(*) FROM (SELECT email FROM users WHERE email IS NOT NULL GROUP BY email HAVING COUNT(*) > 1) duplicates')->fetchColumn();
    if ((int)$duplicates !== 0 || (int)$duplicateEmails !== 0) throw new RuntimeException('Duplicate login/email detected');
    $allTables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($allTables as $table) foreach ($pdo->query('CHECK TABLE `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC) as $health) {
        if ($health['Msg_type'] === 'error' || ($health['Msg_type'] === 'status' && $health['Msg_text'] !== 'OK')) throw new RuntimeException('Post-deployment table health check failed');
    }
    foreach ($fileHashes as $file => $hash) if (hash_file('sha256', base_path($file)) !== $hash) throw new RuntimeException('Protected configuration file changed');
    if ($grantsBefore !== hash('sha256', json_encode($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_NUM)))) throw new RuntimeException('Application grants changed');
    Illuminate\Support\Facades\Mail::assertNothingSent(); Illuminate\Support\Facades\Notification::assertNothingSent();
    $result += ['database' => $identity['db'], 'port' => 3307, 'migration_applied' => basename($migrationPath, '.php'),
        'total_users' => App\Models\User::count(), 'faculty_users' => App\Models\User::where('role', 'faculty')->count(),
        'roster_represented' => 41, 'preserved_roster_user_id' => 45, 'first_login_password_redirects_verified' => count($newIds),
        'repeat_import_created' => 0, 'preexisting_accounts_unchanged' => true, 'preexisting_stable_tables_preserved' => count($before),
        'financial_history_unchanged' => true, 'configuration_and_grants_unchanged' => true, 'external_messages_sent' => 0,
        'directory_total' => $directoryData['members']->total(), 'healthy_tables' => count($allTables),
        'credentials_report' => $report, 'completed_at' => now()->toIso8601String()];
    file_put_contents($directory.'/normal-deployment-verified.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    Illuminate\Support\Facades\DB::purge($temporaryConnection); config(['database.connections.'.$temporaryConnection => null]);
    if ($reportCreated && !$accountsCommitted && is_file($report)) unlink($report);
    $failure = ['stopped' => true, 'phase' => $phase, 'accounts_committed' => $accountsCommitted,
        'reason' => $error instanceof PDOException || $error instanceof Illuminate\Database\QueryException ? 'Database operation failed; inspect recorded deployment state without retrying inserts.' : $error->getMessage()];
    if (isset($directory) && is_dir($directory)) file_put_contents($directory.'/normal-deployment-stopped.json', json_encode($failure, JSON_PRETTY_PRINT));
    fwrite(STDERR, json_encode($failure).PHP_EOL); exit(1);
}
