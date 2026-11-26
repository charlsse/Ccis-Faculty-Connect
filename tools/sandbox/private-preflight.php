<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
try {
    require __DIR__.'/bootstrap.php';
    $db = Illuminate\Support\Facades\DB::connection();
    $db->statement('SET TRANSACTION READ ONLY');
    $db->beginTransaction();
    $users = $db->table('users')->get();
    $synthetic = $users->count() === 3;
    foreach ($users as $u) {
        $synthetic = $synthetic && in_array($u->role, ['faculty','admin','treasurer'], true)
            && $u->faculty_id === 'SYNTH-'.strtoupper($u->role)
            && $u->email === $u->role.'@example.invalid'
            && $u->first_name === 'Synthetic';
    }
    $result = [
        'database' => $db->getDatabaseName(),
        'expected_database' => $db->getDatabaseName() === 'ccis_xendit_test_20261018_111928',
        'synthetic_users_only' => $synthetic,
        'trusted_devices' => $db->table('trusted_devices')->count(),
        'gateway_disabled' => config('payment_gateway.enabled') === false,
        'tunnel_disabled' => config('tunnel.enabled') === false,
        'remote_requests_disabled' => env('FC_SANDBOX_REMOTE_APPROVED', false) === false,
        'sandbox_mode' => config('payment_gateway.environment') === 'sandbox',
        'secret_key_present' => filled(config('payment_gateway.xendit.secret_key')),
        'test_key_prefix' => str_starts_with((string)config('payment_gateway.xendit.secret_key'), 'xnd_development_'),
        'callback_token_present' => filled(config('payment_gateway.xendit.callback_token')),
        'business_id_present' => filled(config('payment_gateway.xendit.business_id')),
        'treasurer_confirmed' => config('payment_gateway.xendit.treasurer_account_confirmed') === true,
        'secure_cookie' => config('session.secure'),
        'http_only_cookie' => config('session.http_only'),
        'same_site' => config('session.same_site'),
    ];
    $db->rollBack();
    echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, "Private preflight failed; no credentials printed.\n"); exit(1);
}
