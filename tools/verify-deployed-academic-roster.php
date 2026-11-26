<?php

// Post-deployment verification only. Never reapplies a migration or creates missing accounts.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
ini_set('display_errors', '0');
$phase = 'verification_context';
try {
    $directory = realpath($argv[1] ?? '');
    $allowedRoot = realpath('C:/Users/Charles/FacultyConnect-Recovery/recurring-backups');
    if (!$directory || !$allowedRoot || !str_starts_with(strtolower($directory), strtolower($allowedRoot).DIRECTORY_SEPARATOR)) throw new RuntimeException('Unexpected protected evidence folder');
    $before = json_decode(file_get_contents($directory.'/normal-deployment-before.json'), true, 32, JSON_THROW_ON_ERROR);
    $backup = json_decode(file_get_contents($directory.'/backup-restoration-verified.json'), true, 32, JSON_THROW_ON_ERROR);
    $report = 'C:/Users/Charles/FacultyConnect-Recovery/recurring-backups/academic-roster-preflight-20261124-134109/new-faculty-credentials.json';
    foreach ([$directory, dirname($report)] as $protectedDirectory) {
        (new Symfony\Component\Process\Process(['powershell.exe', '-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', __DIR__.'/Test-PrivateRosterDirectory.ps1', '-Directory', $protectedDirectory]))->mustRun();
    }
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    $identity = $pdo->query('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery, @@read_only readonly_mode')->fetch(PDO::FETCH_ASSOC);
    if (config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1'
        || $identity['db'] !== 'ccis_local_20261018_164245' || (int)$identity['port'] !== 3307
        || (int)$identity['recovery'] !== 0 || (int)$identity['readonly_mode'] !== 0) throw new RuntimeException('Identity or health mismatch');
    if (hash_file('sha256', $directory.'/main-before.sql') !== $backup['backup_sha256']) throw new RuntimeException('Backup checksum mismatch');
    $migrationName = '2026_11_24_000000_add_academic_roster_provisioning';
    if (hash_file('sha256', database_path('migrations/'.$migrationName.'.php')) !== $backup['migration_sha256']
        || Illuminate\Support\Facades\DB::table('migrations')->where('migration', $migrationName)->count() !== 1) throw new RuntimeException('Migration record/hash mismatch');
    $fingerprint = function (string $table, string $where = '') use ($pdo): array {
        $rows = $pdo->query('SELECT * FROM `'.$table.'`'.$where)->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map(fn ($r) => json_encode($r, JSON_THROW_ON_ERROR), $rows); sort($encoded, SORT_STRING);
        return ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($encoded, JSON_THROW_ON_ERROR))];
    };
    $phase = 'existing_account_and_financial_preservation';
    foreach ($before['stable_fingerprints'] as $table => $expected) {
        $where = match ($table) {
            'users' => ' WHERE id IN ('.implode(',', array_map('intval', $before['original_user_ids'])).')',
            'migrations' => ' WHERE migration <> '.$pdo->quote($migrationName),
            'audit_logs' => " WHERE action <> 'academic_roster.provisioned'",
            default => '',
        };
        if ($fingerprint($table, $where) !== $expected) throw new RuntimeException('Pre-existing stable rows differ in '.$table);
    }
    foreach ($before['protected_file_hashes'] as $file => $hash) if (hash_file('sha256', base_path($file)) !== $hash) throw new RuntimeException('Protected configuration differs');
    $phase = 'read_only_roster_reconciliation';
    $manifest = json_decode(file_get_contents(storage_path('app/private/academic-roster-review.json')), true, 32, JSON_THROW_ON_ERROR);
    $service = app(App\Services\AcademicRosterProvisioner::class);
    $review = $service->review($manifest);
    if (count($review) !== 41 || array_filter($review, fn ($r) => $r['outcome'] !== 'preserve_existing')) throw new RuntimeException('Reconciliation is not exactly 41 preserved accounts; no inserts attempted');
    $markerRows = Illuminate\Support\Facades\DB::table('academic_roster_accounts')->get();
    if ($markerRows->count() !== 40) throw new RuntimeException('Expected 40 provenance records');
    $newIds = $markerRows->pluck('user_id')->map(fn ($id) => (int)$id)->all();
    if (array_intersect($newIds, array_map('intval', $before['original_user_ids']))) throw new RuntimeException('Existing user received roster marker');
    $actorIds = $markerRows->pluck('provisioned_by')->unique()->all();
    if (count($actorIds) !== 1) throw new RuntimeException('Unexpected provisioning actor provenance');
    $actor = App\Models\User::findOrFail($actorIds[0]);
    $phase = 'credential_hashes_and_first_login';
    Illuminate\Support\Facades\Mail::fake(); Illuminate\Support\Facades\Notification::fake();
    config(['session.driver' => 'array', 'cache.default' => 'array']);
    $credentials = json_decode(file_get_contents($report), true, 32, JSON_THROW_ON_ERROR)['credentials'];
    if (count($credentials) !== 40 || count(array_unique(array_column($credentials, 'temporary_password'))) !== 40
        || count(array_unique(array_column($credentials, 'login_id'))) !== 40) throw new RuntimeException('Credential report uniqueness/count mismatch');
    $session = app('session')->driver(); $session->start();
    $verifiedIds = []; $expiries = [];
    foreach ($credentials as $credential) {
        $user = App\Models\User::where('faculty_id', $credential['login_id'])->firstOrFail();
        if (!in_array($user->id, $newIds, true) || !$user->isAcademicRosterDemo() || $user->role !== 'faculty' || $user->status !== 'active'
            || $user->email !== null || $user->email_verified_at !== null || !$user->must_change_password
            || !$user->temporary_password_expires_at?->isFuture() || $user->two_factor_secret !== null
            || $user->two_factor_confirmed_at !== null || $user->two_factor_recovery_codes !== null
            || !Illuminate\Support\Facades\Hash::check($credential['temporary_password'], $user->password)) throw new RuntimeException('Account security state/report verification failed');
        $verifiedIds[] = $user->id; $expiries[] = $user->temporary_password_expires_at->toIso8601String();
        $session->flush(); Illuminate\Support\Facades\Auth::forgetGuards();
        $request = Illuminate\Http\Request::create('http://127.0.0.1:8000/login', 'POST', ['login' => $credential['login_id'], 'password' => $credential['temporary_password']]);
        $request->setLaravelSession($session); $app->instance('request', $request);
        $response = app(App\Http\Controllers\AuthController::class)->login($request);
        if (Illuminate\Support\Facades\Auth::id() !== $user->id || parse_url($response->getTargetUrl(), PHP_URL_PATH) !== '/onboarding/password') throw new RuntimeException('First-login password redirect failed');
    }
    unset($credentials, $credential);
    if (count(array_unique($verifiedIds)) !== 40) throw new RuntimeException('Verified account count mismatch');
    $phase = 'idempotency_no_inserts';
    $usersBeforeRepeat = $fingerprint('users'); $auditBeforeRepeat = $fingerprint('audit_logs');
    // Reconciliation was proved to contain zero creates; this transaction must perform no writes.
    $repeat = $service->provision($manifest, $actor, fn () => throw new RuntimeException('Repeat import requested credentials; no insert retry allowed'));
    if ($repeat['created'] !== 0 || $repeat['matched_existing'] !== 41 || $usersBeforeRepeat !== $fingerprint('users')
        || $auditBeforeRepeat !== $fingerprint('audit_logs')) throw new RuntimeException('Idempotency check failed');
    $phase = 'schema_and_directory_integrity';
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) !== 25) throw new RuntimeException('Unexpected post-migration table set');
    foreach ($tables as $table) foreach ($pdo->query('CHECK TABLE `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC) as $health) {
        if ($health['Msg_type'] === 'error' || ($health['Msg_type'] === 'status' && $health['Msg_text'] !== 'OK')) throw new RuntimeException('Table health failed');
    }
    $columns = $pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email'")->fetchColumn();
    if ($columns !== 'YES' || !array_filter($pdo->query('SHOW INDEX FROM users')->fetchAll(PDO::FETCH_ASSOC), fn ($r) => $r['Column_name'] === 'email' && (int)$r['Non_unique'] === 0)) throw new RuntimeException('Reviewed email schema does not match');
    foreach (['faculty_id', 'email'] as $column) {
        if ((int)$pdo->query('SELECT COUNT(*) FROM (SELECT `'.$column.'` FROM users WHERE `'.$column.'` IS NOT NULL GROUP BY `'.$column.'` HAVING COUNT(*) > 1) duplicate_values')->fetchColumn() !== 0) throw new RuntimeException('Duplicate account identifier');
    }
    $directoryData = app(App\Http\Controllers\DirectoryController::class)->index(Illuminate\Http\Request::create('http://127.0.0.1:8000/dashboard/directory'))->getData();
    $active = App\Models\User::where('status', 'active')->count();
    if ($directoryData['members']->total() !== $active || App\Models\User::whereIn('id', $newIds)->where('status', 'active')->count() !== 40) throw new RuntimeException('Directory membership mismatch');
    Illuminate\Support\Facades\Mail::assertNothingSent(); Illuminate\Support\Facades\Notification::assertNothingSent();
    $result = ['database' => $identity['db'], 'port' => 3307, 'migration_applied' => $migrationName, 'source_roster' => 41,
        'preserved_existing_roster_accounts' => 1, 'preserved_roster_user_id' => 45, 'new_faculty_accounts' => 40,
        'total_users' => App\Models\User::count(), 'active_users' => $active, 'faculty_users' => App\Models\User::where('role', 'faculty')->count(),
        'directory_total' => $directoryData['members']->total(), 'first_login_password_redirects_verified' => 40,
        'repeat_import_created' => 0, 'preexisting_accounts_preserved' => $before['stable_fingerprints']['users']['rows'],
        'preexisting_stable_tables_preserved' => count($before['stable_fingerprints']), 'financial_history_unchanged' => true,
        'configuration_unchanged' => true, 'healthy_tables' => count($tables), 'external_messages_sent' => 0,
        'credential_expiry_earliest' => min($expiries), 'credential_expiry_latest' => max($expiries), 'credentials_report' => $report,
        'verification_note' => 'A strict integer/string identifier comparison in stringified-PDO verification was corrected. No real reconciliation conflict or duplicate insert occurred.',
        'completed_at' => now()->toIso8601String()];
    file_put_contents($directory.'/normal-deployment-verified.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['stopped' => true, 'phase' => $phase, 'reason' => $error instanceof PDOException || $error instanceof Illuminate\Database\QueryException ? 'Database verification failed; no provisioning retry performed.' : $error->getMessage()]).PHP_EOL);
    exit(1);
}
