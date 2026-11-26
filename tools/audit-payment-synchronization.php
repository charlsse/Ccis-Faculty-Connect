<?php

use App\Models\WaterReceipt;
use App\Models\XenditDemoAttempt;
use App\Services\Payments\DemoXenditGateway;
use App\Services\WaterPaymentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only metadata/evidence audit. Never creates sessions, sends mail, or settles an attempt.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$identity = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
if ($identity->db !== 'ccis_local_20261018_164245' || (int) $identity->port !== 3307 || (int) $identity->recovery !== 0) {
    throw new RuntimeException('Unexpected main database');
}
$evidence = ['main_identity_verified' => true, 'main_notifications_schema_ready' => Schema::hasColumn('water_notices', 'event_key'),
    'demo_outbox_ready' => Schema::connection('xendit_demo')->hasTable('notification_outbox'),
    'email_enabled' => (bool) config('water.email_enabled'), 'smtp_configured' => (bool) (config('mail.mailers.smtp.username') && config('mail.mailers.smtp.password')),
    'brevo_host' => str_contains((string) config('mail.mailers.smtp.host'), 'brevo'),
    'wallet_simulation_enabled' => (bool) config('wallet.enabled')];
$evidence['active_payment_managers'] = app(\App\Services\PaymentNotifications::class)->treasurers()->count();
$evidence['confirmed_demo_payer_roles'] = XenditDemoAttempt::where('status', 'confirmed')->get()
    ->map(fn ($attempt) => \App\Models\User::find($attempt->user_id)?->role)->countBy()->all();
$beforeFiles = glob(storage_path('app/private/payment-stabilization-*/before.json'));
sort($beforeFiles);
$beforeFile = end($beforeFiles);
if ($beforeFile) {
    $before = json_decode(file_get_contents($beforeFile), true);
    $matches = [];
    foreach ($before as $table => $old) {
        $rows = DB::table($table)->orderBy('id')->get();
        $matches[$table] = $old === ['rows' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())];
    }
    $evidence['protected_tables_unchanged'] = $matches;
}
$receipts = WaterReceipt::all();
$evidence['actual_receipts'] = $receipts->whereIn('payment_method', ['cash', 'qr'])->count();
$evidence['actual_receipt_minor_total'] = $receipts->whereIn('payment_method', ['cash', 'qr'])->sum(fn ($r) => WaterPaymentService::cents($r->amount));
$evidence['receipt_link_issues'] = WaterReceipt::whereDoesntHave('member', fn ($m) => $m->where('status', 'paid'))
    ->orWhereDoesntHave('submission', fn ($s) => $s->where('status', 'approved')->whereColumn('submitted_amount', 'water_receipts.amount')->whereColumn('water_member_id', 'water_receipts.water_member_id'))->count();
$evidence['demo_status_counts'] = XenditDemoAttempt::selectRaw('status, COUNT(*) total')->groupBy('status')->pluck('total', 'status')->all();
$evidence['existing_provider_checks'] = [];
if (in_array('--provider-read', $argv, true)) {
    foreach (XenditDemoAttempt::where('status', 'confirmed')->limit(3)->get() as $attempt) {
        try {
            $verified = app(DemoXenditGateway::class)->retrievePayment($attempt->provider_session_id);
            $evidence['existing_provider_checks'][] = ['verified' => $verified['status'] === 'succeeded' && $verified['amount_minor'] === $attempt->amount_minor && $verified['reference'] === $attempt->display_reference,
                'sandbox' => $verified['environment'] === 'sandbox'];
        } catch (Throwable $e) {
            $evidence['existing_provider_checks'][] = ['verified' => false, 'exception_type' => get_class($e)];
        }
    }
}
echo json_encode($evidence, JSON_PRETTY_PRINT).PHP_EOL;
