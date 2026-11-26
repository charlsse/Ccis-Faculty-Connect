<?php

use PragmaRX\Google2FA\Google2FA;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2).'/packages/autoload.php';
$root = dirname(getenv('FC_WALLET_CONFIG'));
$checks = [];
function check($ok, $name)
{
    global $checks;
    $checks[$name] = (bool) $ok;
    if (! $ok) {
        throw new RuntimeException($name);
    }
}
function requestLocal($method, $path, $data = [], $jar = 'guest')
{
    global $root;
    $c = curl_init('https://localhost:8444'.$path);
    $headers = [];
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15, CURLOPT_CAINFO => $root.'/localhost.crt', CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_RESOLVE => ['localhost:8444:127.0.0.1'], CURLOPT_COOKIEJAR => $root.'/'.$jar.'.cookies', CURLOPT_COOKIEFILE => $root.'/'.$jar.'.cookies', CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
        $headers[] = $line;

        return strlen($line);
    }]);
    if ($method === 'POST') {
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($data)]);
    }
    $body = curl_exec($c);
    $status = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $ssl = curl_getinfo($c, CURLINFO_SSL_VERIFYRESULT);
    curl_close($c);

    return ['body' => $body, 'status' => $status, 'headers' => $headers, 'ssl' => $ssl];
}
function csrf($body)
{
    preg_match('/name="_token" value="([^"]+)"/', $body, $m);

    return html_entity_decode($m[1] ?? '');
}
try {
    $credentials = json_decode(file_get_contents($root.'/test-accounts.private.json'), true);
    $r = requestLocal('GET', '/login');
    check($r['ssl'] === 0 && $r['status'] === 200, 'TLS hostname and certificate');
    $cookies = array_filter($r['headers'], fn ($h) => str_starts_with(strtolower($h), 'set-cookie: facultyconnect_'));
    check(count($cookies) > 0, 'Separate session cookie');
    foreach ($cookies as $cookie) {
        check(str_contains(strtolower($cookie), 'secure') && str_contains(strtolower($cookie), 'httponly') && str_contains(strtolower($cookie), 'samesite=lax'), 'Cookie protections');
    }
    check(requestLocal('GET', '/.env')['status'] === 404, 'Private configuration denied');
    check(requestLocal('POST', '/webhooks/payments/xendit')['status'] === 404, 'Provider callbacks unavailable');
    check(requestLocal('GET', '/dashboard/treasury')['status'] === 302, 'Unauthenticated wallet denied');
    foreach (['faculty', 'admin', 'treasurer'] as $role) {
        $r = requestLocal('GET', '/login', [], $role);
        $r = requestLocal('POST', '/login', ['_token' => csrf($r['body']), 'login' => $role.'@wallet.invalid', 'password' => $credentials['password']], $role);
        check($r['status'] === 302, $role.' password login');
        $r = requestLocal('GET', '/two-factor-challenge', [], $role);
        check($r['status'] === 200, $role.' 2FA challenge');
        $code = (new Google2FA)->getCurrentOtp($credentials['totp_secret']);
        $r = requestLocal('POST', '/two-factor-challenge', ['_token' => csrf($r['body']), 'code' => $code], $role);
        check($r['status'] === 302, $role.' TOTP accepted');
        $r = requestLocal('GET', '/dashboard/water', [], $role);
        check($r['status'] === 200, $role.' contributions preserved');
        $r = requestLocal('GET', '/dashboard/treasury', [], $role);
        check($r['status'] === ($role === 'faculty' ? 403 : 200), $role.' wallet permission');
        if ($role === 'admin') {
            check(! str_contains($r['body'], 'Review simulated withdrawal'), 'Admin view only');
        }
        if ($role === 'treasurer') {
            check(str_contains($r['body'], 'Available · SIMULATED') && str_contains($r['body'], '₱99.00'), 'Wallet totals rendered');
            file_put_contents($root.'/wallet-rendered.private.html', $r['body']);
            $r = requestLocal('POST', '/dashboard/treasury/review', ['_token' => csrf($r['body']), 'collection' => 1, 'destination' => 1, 'amount' => '10.00'], $role);
            check($r['status'] === 200 && str_contains($r['body'], 'Locked destination'), 'Review destination and amount');
            preg_match('/name="key" value="([^"]+)"/', $r['body'], $m);
            $key = $m[1] ?? '';
            $token = csrf($r['body']);
            $r = requestLocal('POST', '/dashboard/treasury/confirm', ['_token' => $token, 'key' => $key, 'password' => $credentials['password']], $role);
            check($r['status'] === 302, 'Reauthentication confirmation');
            $r = requestLocal('POST', '/dashboard/treasury/confirm', ['_token' => $token, 'key' => $key, 'password' => $credentials['password']], $role);
            check($r['status'] === 302, 'Duplicate confirmation safe');
            $r = requestLocal('GET', '/dashboard/treasury', [], $role);
            check(str_contains($r['body'], '₱89.00'), 'Funds reserved once');
            preg_match_all('#action="https://localhost:8444/dashboard/treasury/withdrawals/([0-9]+)/simulate"#', $r['body'], $matches);
            $id = max(array_map('intval', $matches[1]));
            foreach (['processing', 'completed'] as $outcome) {
                $r = requestLocal('POST', '/dashboard/treasury/withdrawals/'.$id.'/simulate', ['_token' => csrf($r['body']), 'outcome' => $outcome], $role);
                check($r['status'] === 302, 'Simulated '.$outcome);
                $r = requestLocal('GET', '/dashboard/treasury', [], $role);
            }
            check(str_contains($r['body'], 'Completed'), 'Completed history rendered');
        }
        $r = requestLocal('GET', '/dashboard', [], $role);
        $r = requestLocal('POST', '/logout', ['_token' => csrf($r['body'])], $role);
        check($r['status'] === 302, $role.' logout');
        check(requestLocal('GET', '/dashboard/treasury', [], $role)['status'] === 302, $role.' logged out access blocked');
    }
    file_put_contents($root.'/https-validation.json', json_encode(['checks' => $checks, 'total' => count($checks), 'certificate_validation_disabled' => false], JSON_PRETTY_PRINT));
    echo json_encode(['https_checks' => count($checks), 'passed' => true])."\n";
} catch (Throwable $e) {
    file_put_contents($root.'/https-validation-failed.json',json_encode(['checks' => $checks, 'exception' => get_class($e)],JSON_PRETTY_PRINT));
    echo json_encode(['failed_check' => array_key_last($checks), 'exception' => get_class($e)])."\n";
    exit(1);
}
