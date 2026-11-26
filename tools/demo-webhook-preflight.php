<?php
// Operational Test Mode activation and stateless callback probe; no payment creation.
require __DIR__.'/demo-identity.php';
$mode = $argv[1] ?? 'probe';
$origin = 'https://skid-porridge-snoring.ngrok-free.dev';
if (!in_array($mode, ['enable', 'disable', 'probe', 'probe-local'], true)) throw new RuntimeException('Invalid mode');
if ($app->configurationIsCached()) throw new RuntimeException('Cached configuration requires review');
if (in_array($mode, ['enable', 'disable'], true)) {
    $path = storage_path('app/private/xendit-demo-config.json');
    $settings = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    config(['xendit_demo.enabled' => $mode === 'enable']);
    if ($mode === 'enable' && (config('xendit_demo.return_base') !== $origin || !app(App\Services\Payments\XenditDemoService::class)->ready())) throw new RuntimeException('Activation guard failed');
    $settings['enabled'] = $mode === 'enable';
    if (file_put_contents($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) throw new RuntimeException('Configuration write failed');
    echo 'Demo activation: '.$mode.PHP_EOL;
    exit;
}
if (!app(App\Services\Payments\XenditDemoService::class)->ready()) throw new RuntimeException('Demo disabled or ledger unavailable');
$ref = '11111111-1111-4111-8111-111111111111';
$session = 'ps-000000000000000000000000';
if (App\Models\XenditDemoAttempt::where('reference', $ref)->orWhere('provider_session_id', $session)->exists()) throw new RuntimeException('Fixture collides with real attempt');
$before = hash_file('sha256', storage_path('app/private/xendit-demo.sqlite'));
if ($mode === 'probe-local') $origin = 'http://127.0.0.1:8005';
foreach (['COMPLETED' => 'payment_session.completed', 'EXPIRED' => 'payment_session.expired'] as $status => $event) {
    $body = ['business_id' => config('xendit_demo.credentials.business_id'), 'event' => $event,
        'data' => ['payment_session_id' => $session, 'business_id' => config('xendit_demo.credentials.business_id'),
            'reference_id' => 'FC-WATER-'.$ref, 'session_type' => 'PAY', 'mode' => 'PAYMENT_LINK',
            'currency' => 'PHP', 'country' => 'PH', 'amount' => 1, 'status' => $status]];
    // Also exercise the observed dashboard differences, not only canonical sessions.
    $sample = $body;
    $sample['business_id'] = 'sample-merchant';
    unset($sample['data']['business_id']);
    $sample['data'] = array_replace($sample['data'], ['payment_session_id' => 'sample-session', 'reference_id' => 'sample-reference', 'session_type' => 'SAVE', 'currency' => 'IDR', 'country' => 'ID']);
    $sample['data']['id'] = $sample['data']['payment_session_id'];
    unset($sample['data']['payment_session_id']);
    foreach (['canonical' => $body, 'placeholder' => $sample] as $shape => $payload) {
    foreach ([false, true] as $authenticated) {
        // A fresh client prevents withHeaders() from merging wrong/correct token values.
        $response = Illuminate\Support\Facades\Http::acceptJson()->asJson()->withOptions(['allow_redirects' => false])->connectTimeout(10)->timeout(25)
            ->withHeaders(['x-callback-token' => $authenticated ? config('xendit_demo.credentials.callback_token') : 'invalid-preflight-token', 'ngrok-skip-browser-warning' => '1'])
            ->post($origin.'/webhooks/payments/xendit-demo', $payload);
        echo $event.' '.$shape.' '.($authenticated ? 'authenticated' : 'wrong-token').' HTTP '.$response->status().PHP_EOL;
        if ($response->status() !== ($authenticated ? 200 : 503)) throw new RuntimeException('Public callback preflight failed');
    }
    }
}
if ($before !== hash_file('sha256', storage_path('app/private/xendit-demo.sqlite'))) throw new RuntimeException('Ledger file changed during probe');
echo 'PASS: unknown sample events caused no ledger writes; not a provider payment.'.PHP_EOL;
