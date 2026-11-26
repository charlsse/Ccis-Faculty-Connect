<?php

if (PHP_SAPI !== 'cli') {
    exit;
}
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WaterCollectionController;
use App\Models\User;
use App\Models\WaterMember;
use App\Models\WaterReceipt;
use App\Services\TwoFactorService;
use App\Services\Wallet\WalletService;
use App\Services\WaterPaymentService;
use App\Services\WaterReceiptPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;

try {
    $app = require __DIR__.'/bootstrap.php';
    $root = dirname(getenv('FC_UNIFIED_CONFIG'));
    $db = DB::connection();
    $checks = [];
    $check = function ($name, $ok) use (&$checks) {
        $checks[$name] = (bool) $ok;
        if (! $ok) {
            throw new RuntimeException($name);
        }
    };
    $baseline = json_decode(file_get_contents($root.'/source-before.json'), true);
    $concurrencyUserId = null;
    $compare = function () use ($baseline, $db, &$concurrencyUserId) {
        foreach ($baseline as $t => $old) {
            $rows = $db->table($t)->when($t === 'users' && $concurrencyUserId, fn ($q) => $q->where('id', '!=', $concurrencyUserId))->get()->map(fn ($r) => (array) $r)->all();
            $h = array_map(fn ($r) => hash('sha256', json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), $rows);
            sort($h);
            if (count($rows) !== $old['count'] || hash('sha256', implode("\n", $h)) !== $old['hash']) {
                return false;
            }
        }

return true;
    };
    $check('source_copy_matches_before', $compare());
    $check('empty_sessions', DB::table('sessions')->count() === 0);
    putenv('FC_WALLET_SCHEMA_APPROVED_DB='.$db->getDatabaseName());
    $migration = require dirname(__DIR__, 2).'/database/reviewed/wallet/2026_10_19_010000_create_treasury_accounting.php';
    if (Schema::hasTable('wallet_accounts')) {
        $migration->down();
    } // Refuses any populated history.
    $migration->up();
    $check('no_fictional_balance', DB::table('wallet_accounts')->count() === 0 && DB::table('wallet_entries')->count() === 0);
    $migration->down();
    $check('empty_rollback', ! Schema::hasTable('wallet_accounts'));
    $migration->up();
    $db->beginTransaction();
    View::share('errors', new ViewErrorBag);
    // Password and 2FA tests use disposable accounts within a transaction that is rolled back.
    $session = app('session')->driver();
    $session->start();
    $authController = app(AuthController::class);
    foreach (['faculty', 'treasurer', 'admin'] as $role) {
        Auth::forgetGuards();
        $password = bin2hex(random_bytes(20));
        $secret = app(TwoFactorService::class)->generateSecret();
        $u = User::factory()->create(['role' => $role, 'email' => $role.'@unified-review.invalid', 'password' => $password, 'two_factor_secret' => $secret]);
        if ($role === 'treasurer') {
            $testTreasurer = $u;
        }
        $request = Request::create('/login', 'POST', ['login' => $u->email, 'password' => $password]);
        $request->setLaravelSession($session);
        $app->instance('request', $request);
        Auth::guard()->setRequest($request);
        $response = $authController->login($request);
        $check($role.'_password_challenge', $response->getStatusCode() === 302 && ! Auth::check() && $session->get('pending_login_user_id') === $u->id);
        $request = Request::create('/two-factor-challenge', 'POST', ['code' => (new Google2FA)->getCurrentOtp($secret)]);
        $request->setLaravelSession($session);
        $app->instance('request', $request);
        Auth::guard()->setRequest($request);
        $response = $authController->challenge($request);
        $check($role.'_2fa', Auth::id() === $u->id && $response->getStatusCode() === 302);
        $request->setUserResolver(fn () => $u);
        $view = app(WaterCollectionController::class)->index($request);
        $check($role.'_payment_page', strlen($view->render()) > 1000);
        if ($role !== 'faculty') {
            $view = app(WalletController::class)->index($request, new WalletService);
            $html = $view->render();
            $check($role.'_wallet', str_contains($html, 'Verified contribution history') && str_contains($html, 'Request withdrawal unavailable'));
        } else {
            try {
                app(WalletController::class)->index($request, new WalletService);
                $check('faculty_denied', false);
            } catch (HttpException $e) {
                $check('faculty_denied', $e->getStatusCode() === 403);
            }
        }
        $authController->logout($request);
        $check($role.'_logout', ! Auth::check());
    }
    $treasurer = $testTreasurer;
    Auth::forgetGuards();
    Auth::guard()->setUser($treasurer);
    request()->setUserResolver(fn () => $treasurer);
    $member = WaterMember::whereIn('status', ['unpaid', 'rejected'])->whereHas('collection', fn ($q) => $q->whereIn('status', ['published', 'closed']))->first();
    if ($member) {
        $before = WaterReceipt::count();
        app(WaterPaymentService::class)->cash($member, ['submitted_amount' => $member->assigned_amount, 'paid_at' => now()]);
        $member->refresh();
        $check('manual_cash_receipt', $member->status === 'paid' && WaterReceipt::count() === $before + 1);
        $pdf = app(WaterReceiptPdf::class)->render($member->receipt);
        $check('receipt_pdf', str_starts_with($pdf, '%PDF-'));
        try {
            app(WaterPaymentService::class)->cash($member, ['submitted_amount' => $member->assigned_amount, 'paid_at' => now()]);
            $check('duplicate_manual_rejected', false);
        } catch (ValidationException $e) {
            $check('duplicate_manual_rejected', true);
        }
    }
    $check('reports_render', strlen(app(WaterCollectionController::class)->reports()->render()) > 1000);
    $db->rollBack();
    $check('source_records_preserved_after_flow_tests', $compare());
    $concurrencyUserId = User::factory()->create(['role' => 'treasurer', 'email' => 'concurrency@unified-review.invalid', 'password' => bin2hex(random_bytes(24))])->id;
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $p = proc_open([PHP_BINARY, __DIR__.'/quota-worker.php', (string) Str::uuid(), (string) $concurrencyUserId], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $children[] = [$p, $pipes];
    }
    $out = [];
    foreach ($children as [$p,$pipes]) {
        $out[] = trim(stream_get_contents($pipes[1]));
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $check('worker_'.count($out), proc_close($p) === 0 && $err === '');
    }sort($out);
    $check('concurrent_quota_stale_guard', $out === ['saved', 'stale']);
    $check('one_audit', DB::table('wallet_audits')->count() === 1);
    try {
        $migration->down();
        $check('populated_rollback_refused', false);
    } catch (RuntimeException $e) {
        $check('populated_rollback_refused', true);
    }
    foreach ($db->select('SHOW TABLES') as $row) {
        $t = array_values((array) $row)[0];
        foreach ($db->select('CHECK TABLE `'.$t.'` EXTENDED') as $r) {
            $check('table_'.$t, $r->Msg_type === 'status' && $r->Msg_text === 'OK');
        }
    }
    $fks = $db->select('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL');
    foreach ($fks as $f) {
        $n = $db->selectOne('SELECT COUNT(*) n FROM `'.$f->TABLE_NAME.'` c LEFT JOIN `'.$f->REFERENCED_TABLE_NAME.'` p ON c.`'.$f->COLUMN_NAME.'`=p.`'.$f->REFERENCED_COLUMN_NAME.'` WHERE c.`'.$f->COLUMN_NAME.'` IS NOT NULL AND p.`'.$f->REFERENCED_COLUMN_NAME.'` IS NULL')->n;
        $check('fk_'.$f->TABLE_NAME.'_'.$f->COLUMN_NAME, (int) $n === 0);
    }
    $check('all_source_records_preserved', $compare());
    $check('no_remote_activation', ! config('payment_gateway.enabled') && ! config('tunnel.enabled') && ! env('FC_SANDBOX_REMOTE_APPROVED', false));
    Http::assertNothingSent();
    file_put_contents($root.'/validation.json', json_encode(['database' => $db->getDatabaseName(), 'checks' => $checks, 'count' => count($checks), 'all_passed' => true], JSON_PRETTY_PRINT));
    echo json_encode(['checks' => count($checks), 'all_passed' => true]).PHP_EOL;
} catch (Throwable $e) {
    if (isset($db) && $db->transactionLevel()) {
        $db->rollBack();
    }if (isset($root)) {
        file_put_contents($root.'/validation-failed.private.json', json_encode(['checks' => $checks ?? [], 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]));
    }echo json_encode(['failed_check' => isset($checks) ? array_key_last($checks) : null, 'exception' => get_class($e)]).PHP_EOL;
    exit(1);
}
