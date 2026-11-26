<?php
// Read-only connectivity check. Never prints keys or provider response bodies.
require __DIR__.'/demo-identity.php';
$key = config('xendit_demo.credentials.secret_key');
if (!is_string($key) || !str_starts_with($key, 'xnd_development_')) throw new RuntimeException('Test credential required');
echo 'Callback token staged: '.(strlen((string)config('xendit_demo.credentials.callback_token')) >= 16 ? 'yes' : 'no').PHP_EOL;
echo 'Approved return origin: '.(config('xendit_demo.return_base') === 'https://skid-porridge-snoring.ngrok-free.dev' ? 'yes' : 'no').PHP_EOL;
try {
    $response = Illuminate\Support\Facades\Http::withBasicAuth($key, '')->acceptJson()
        ->withOptions(['allow_redirects' => false])->connectTimeout(10)->timeout(20)
        ->get('https://api.xendit.co/sessions/ps-000000000000000000000000');
    echo 'Read-only session probe HTTP: '.$response->status().PHP_EOL;
    $code = $response->json('error_code');
    if (is_string($code) && preg_match('/^[A-Z0-9_]{1,80}$/D', $code)) echo 'Provider error code: '.$code.PHP_EOL;
} catch (Throwable $e) {
    echo 'Provider connectivity failed; no transaction created.'.PHP_EOL;
    exit(1);
}
