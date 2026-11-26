<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Payment Sessions, test keys only. No redirect or webhook payload settles a payment. */
class XenditGateway implements PaymentGatewayInterface
{
    protected function credential(string $key): mixed { return config('payment_gateway.xendit.'.$key); }
    public function available(): bool
    {
        $connection = config('database.default');
        $database = config("database.connections.$connection.database");
        $isolated = app()->environment('testing') && $connection === 'sqlite' && $database === ':memory:';
        if (! $isolated) {
            $isolated = $connection === 'mysql' && is_string($database)
                && preg_match('/^ccis_xendit_test_[a-zA-Z0-9_]+$/D', $database)
                && $database === $this->credential('test_database')
                && config('database.connections.mysql.host') === '127.0.0.1'
                && (int) config('database.connections.mysql.port') === 3307;
        }
        return (bool) ($isolated && ! app()->environment('production')
            && parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https'
            && config('payment_gateway.enabled') && config('payment_gateway.provider') === 'xendit'
            && config('payment_gateway.environment') === 'sandbox' && config('payment_gateway.sandbox_data_confirmed')
            && $this->credential('treasurer_account_confirmed')
            && preg_match('/^[a-f0-9]{24}$/D', (string) $this->credential('business_id'))
            && str_starts_with((string) $this->credential('secret_key'), 'xnd_development_')
            && strlen((string) $this->credential('callback_token')) >= 16);
    }

    protected function assertDatabaseIdentity(): void
    {
        if (! app()->environment('testing')) {
            $identity = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
            if ($identity->db !== $this->credential('test_database') || (int) $identity->port !== 3307 || (int) $identity->recovery !== 0) {
                throw new GatewayUnavailable('Test database identity mismatch.');
            }
        }
    }

    protected function returnBase(): string { return (string) config('app.url'); }
    protected function attemptModel(): string { return \App\Models\WaterGatewayAttempt::class; }

    protected function allowUnusedExpiredRequest(): bool { return false; }

    private function request(string $method, string $path, array $body = []): array
    {
        if (! $this->available()) throw new GatewayUnavailable('Xendit test payments are unavailable.');
        // Verify the actual target too; environment labels alone cannot protect recovered data.
        $this->assertDatabaseIdentity();
        try {
            $client = Http::baseUrl('https://api.xendit.co')->acceptJson()->asJson()
                ->withBasicAuth($this->credential('secret_key'), '')
                ->withOptions(['allow_redirects' => false])->connectTimeout(3)->timeout(10);
            if (str_starts_with($path, '/v3/')) $client = $client->withHeaders(['api-version' => '2024-11-11']);
            // Never retry creation automatically: a lost response may still have created a session.
            $response = $method === 'POST'
                ? ($body === [] ? $client->withBody('{}', 'application/json')->post($path) : $client->post($path, $body))
                : $client->get($path);
            if (! $response->successful() || ! is_array($response->json())) throw new \RuntimeException;
            return $response->json();
        } catch (\Throwable $e) {
            // Provider bodies/headers may contain credentials and personal information.
            throw new GatewayUnavailable('Xendit confirmation is temporarily unavailable.');
        }
    }

    public static function safeCheckoutUrl(?string $url): bool
    {
        $parts = is_string($url) ? parse_url($url) : false;
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https'
            && in_array($parts['host'] ?? '', ['checkout-staging.xendit.co', 'dev.xen.to'], true)
            && ! isset($parts['user']) && ! isset($parts['pass']) && (! isset($parts['port']) || $parts['port'] === 443);
    }

    private function minor(mixed $amount): int
    {
        if ((! is_int($amount) && ! is_float($amount)) || ! is_finite((float) $amount)
            || $amount < 0 || $amount > 10000000 || abs($amount * 100 - round($amount * 100)) > 0.00001) {
            throw new GatewayUnavailable('Invalid provider amount.');
        }
        return (int) round($amount * 100);
    }

    private function session(array $data, string $id): array
    {
        if (($data['payment_session_id'] ?? '') !== $id || ! preg_match('/^ps-[a-f0-9]{24}$/D', $id)
            || ($data['business_id'] ?? '') !== $this->credential('business_id')
            || ($data['session_type'] ?? '') !== 'PAY' || ($data['mode'] ?? '') !== 'PAYMENT_LINK'
            || ($data['currency'] ?? '') !== 'PHP' || ($data['country'] ?? '') !== 'PH'
            || ! is_string($data['reference_id'] ?? null) || ! in_array($data['status'] ?? '', ['ACTIVE', 'COMPLETED', 'EXPIRED', 'CANCELED'], true)) {
            throw new GatewayUnavailable('Provider session identity mismatch.');
        }
        $this->minor($data['amount'] ?? null);
        return $data;
    }

    public function createCheckout(string $reference, int $amountMinor, string $currency, string $returnUrl): array
    {
        if ($currency !== 'PHP' || $amountMinor < 100 || $amountMinor > 1000000000
            || ! preg_match('/^FC-WATER-[a-f0-9-]{36}$/D', $reference)
            || parse_url($returnUrl, PHP_URL_SCHEME) !== 'https'
            || parse_url($returnUrl, PHP_URL_HOST) !== parse_url($this->returnBase(), PHP_URL_HOST)) {
            throw new GatewayUnavailable('A configured HTTPS test checkout is required.');
        }
        $data = $this->request('POST', '/sessions', [
            'reference_id' => $reference, 'session_type' => 'PAY', 'mode' => 'PAYMENT_LINK',
            'amount' => $amountMinor / 100, 'currency' => 'PHP', 'country' => 'PH',
            'capture_method' => 'AUTOMATIC', 'allow_save_payment_method' => 'DISABLED',
            // Only synthetic accounts are permitted in this test integration. No faculty PII leaves the server.
            'customer' => ['reference_id' => str_replace('-', '', $reference), 'type' => 'INDIVIDUAL',
                'individual_detail' => ['given_names' => 'FacultyConnect', 'surname' => 'Test']],
            'description' => 'FacultyConnect TEST contribution - '.$reference,
            'success_return_url' => $returnUrl, 'cancel_return_url' => $returnUrl,
        ]);
        if (! is_string($data['payment_session_id'] ?? null)) throw new GatewayUnavailable('Missing session identity.');
        $data = $this->session($data, $data['payment_session_id']);
        if ($data['reference_id'] !== $reference || $this->minor($data['amount']) !== $amountMinor
            || $data['status'] !== 'ACTIVE' || ! self::safeCheckoutUrl($data['payment_link_url'] ?? null)) {
            throw new GatewayUnavailable('Unconfirmed test checkout.');
        }
        return ['payment_id' => $data['payment_session_id'], 'checkout_url' => $data['payment_link_url']];
    }

    public function retrievePayment(string $paymentId): array
    {
        if (! preg_match('/^ps-[a-f0-9]{24}$/D', $paymentId)) throw new GatewayUnavailable('Invalid session identity.');
        $data = $this->session($this->request('GET', '/sessions/'.$paymentId), $paymentId);
        $result = ['payment_id' => $paymentId, 'reference' => $data['reference_id'], 'amount_minor' => $this->minor($data['amount']),
            'currency' => $data['currency'], 'environment' => 'sandbox', 'status' => 'pending', 'paid_at' => null, 'charge_id' => null];
        if ($data['status'] === 'COMPLETED') {
            $requestId = $data['latest_payment_request_id'] ?? $data['payment_request_id'] ?? '';
            if (! is_string($requestId) || ! preg_match('/^pr-[a-f0-9-]{36}$/D', $requestId)) throw new GatewayUnavailable('Missing payment identity.');
            $payment = $this->request('GET', '/v3/payment_requests/'.$requestId);
            if (($payment['payment_request_id'] ?? '') !== $requestId
                || ($payment['business_id'] ?? '') !== $data['business_id']
                || ($payment['currency'] ?? '') !== 'PHP' || ($payment['country'] ?? '') !== 'PH'
                || ($payment['type'] ?? '') !== 'PAY' || ($payment['capture_method'] ?? '') !== 'AUTOMATIC'
                || $this->minor($payment['request_amount'] ?? null) !== $result['amount_minor']) {
                throw new GatewayUnavailable('Payment identity mismatch.');
            }
            if (($payment['status'] ?? '') !== 'SUCCEEDED') return $result;
            $charge = $payment['latest_payment_id'] ?? '';
            if (! is_string($charge) || ! preg_match('/^py-[a-f0-9-]{36}$/D', $charge)
                || ($data['payment_id'] ?? null) !== $charge) throw new GatewayUnavailable('Payment identity mismatch.');
            $paymentRequest = $payment;
            $payment = $this->request('GET', '/v3/payments/'.$charge);
            if (($payment['payment_id'] ?? null) !== $charge
                || ($payment['payment_request_id'] ?? null) !== $requestId
                || ($payment['business_id'] ?? null) !== $data['business_id']
                || ($payment['currency'] ?? null) !== 'PHP' || ($payment['country'] ?? null) !== 'PH'
                || ($payment['type'] ?? null) !== 'PAY' || ($payment['capture_method'] ?? null) !== 'AUTOMATIC'
                || $this->minor($payment['request_amount'] ?? null) !== $result['amount_minor']) {
                throw new GatewayUnavailable('Captured payment identity mismatch.');
            }
            if (($payment['status'] ?? null) !== 'SUCCEEDED') throw new GatewayUnavailable('Successful payment not confirmed.');
            // If both resources expose capture evidence, conflicting evidence must not be ignored.
            if (isset($paymentRequest['captures']) && $paymentRequest['captures'] !== ($payment['captures'] ?? null)) {
                throw new GatewayUnavailable('Conflicting capture evidence.');
            }
            $total = 0; $paidAt = null;
            $captureIds = [];
            if (! is_array($payment['captures'] ?? null) || ! array_is_list($payment['captures']) || $payment['captures'] === []) throw new GatewayUnavailable('Capture not confirmed.');
            foreach ($payment['captures'] ?? [] as $capture) {
                if (! is_array($capture)) throw new GatewayUnavailable('Invalid capture.');
                // Documented as an opaque string, not a prefix/UUID contract. Bound and reject whitespace/control bytes.
                $id = $capture['capture_id'] ?? null;
                if (! is_string($id) || $id === '' || strlen($id) > 255 || preg_match('/[\s\x00-\x1f\x7f]/u', $id) !== 0
                    || in_array($id, $captureIds, true)) throw new GatewayUnavailable('Invalid or duplicate capture identity.');
                $captureIds[] = $id;
                // No per-capture status is required by the API. An unexpected contradictory status fails closed.
                if (array_key_exists('status', $capture) && $capture['status'] !== 'SUCCEEDED') throw new GatewayUnavailable('Ambiguous capture status.');
                $amount = $this->minor($capture['capture_amount'] ?? null);
                if ($amount <= 0) throw new GatewayUnavailable('Invalid capture amount.');
                $total += $amount;
                $timestamp = $capture['capture_timestamp'] ?? '';
                if (! is_string($timestamp) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/D', $timestamp)) throw new GatewayUnavailable('Missing capture timestamp.');
                try { $time = new \DateTimeImmutable($timestamp); } catch (\Exception $e) { throw new GatewayUnavailable('Invalid capture timestamp.'); }
                $errors = \DateTimeImmutable::getLastErrors();
                if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) throw new GatewayUnavailable('Invalid capture timestamp.');
                if (! $paidAt || $time > $paidAt) $paidAt = $time;
            }
            if ($total !== $result['amount_minor'] || ! $paidAt) throw new GatewayUnavailable('Capture not confirmed.');
            $result = array_replace($result, ['status' => 'succeeded', 'paid_at' => $paidAt, 'charge_id' => $charge]);
            if (is_string($payment['channel_code'] ?? null) && preg_match('/^[A-Z0-9_]{1,64}$/D', $payment['channel_code'])) {
                $result['payment_method'] = $payment['channel_code'];
            }
        } elseif ($data['status'] === 'EXPIRED') {
            $result['checkout_expired'] = true;
            // Inconsistent/late expiry with a captured-payment reference is never safe to release.
            if (! empty($data['payment_id'])) return $result;
            $requestId = $data['latest_payment_request_id'] ?? $data['payment_request_id'] ?? null;
            if ($requestId === null) {
                $result['status'] = 'expired';
            } elseif (is_string($requestId) && preg_match('/^pr-[a-f0-9-]{36}$/D', $requestId)) {
                $payment = $this->request('GET', '/v3/payment_requests/'.$requestId);
                if (($payment['payment_request_id'] ?? '') !== $requestId || ($payment['business_id'] ?? '') !== $data['business_id']
                    || ($payment['currency'] ?? '') !== 'PHP' || ($payment['country'] ?? '') !== 'PH' || ($payment['type'] ?? '') !== 'PAY'
                    || $this->minor($payment['request_amount'] ?? null) !== $result['amount_minor']) {
                    throw new GatewayUnavailable('Expired payment identity mismatch.');
                }
                // Test-only demo requests may remain REQUIRES_ACTION after session expiry.
                // With no payment or capture, the expired hosted session can be replaced.
                if ($this->allowUnusedExpiredRequest() && ($payment['status'] ?? '') === 'REQUIRES_ACTION'
                    && empty($payment['captures']) && empty($payment['latest_payment_id']) && empty($payment['latest_payment'])) {
                    $result['status'] = 'expired';
                }
                // Expired checkout must not free a contribution with an in-flight/captured payment.
                if (in_array($payment['status'] ?? '', ['FAILED', 'EXPIRED', 'CANCELED'], true) && empty($payment['captures'])) $result['status'] = 'expired';
            } else {
                throw new GatewayUnavailable('Expired payment identity is unresolved.');
            }
        }
        // CANCELED or a failed payment attempt stays reserved for explicit reconciliation.
        return $result;
    }

    protected function ignoreUnrelatedWebhook(array $body): bool { return false; }

    public function verifiedWebhookPaymentId(Request $request): string
    {
        if (! $this->available()) throw new GatewayUnavailable('Xendit test payments are disabled.');
        if (! hash_equals((string) $this->credential('callback_token'), (string) $request->header('x-callback-token'))) {
            throw new \Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException('CallbackToken', 'Invalid webhook authentication.');
        }
        if (strlen($request->getContent()) > 65536 || ! $request->isJson()) throw new \UnexpectedValueException;
        $body = json_decode($request->getContent(), true, 32, JSON_THROW_ON_ERROR);
        // Demo-only routing may acknowledge unrelated authenticated dashboard samples.
        // A correlated event always continues through every strict validation below.
        if (is_array($body) && $this->ignoreUnrelatedWebhook($body)) return '';
        if (! is_array($body) || ($body['business_id'] ?? '') !== $this->credential('business_id') || ! is_string($body['event'] ?? null)) throw new \UnexpectedValueException;
        if (! in_array($body['event'], ['payment_session.completed', 'payment_session.expired'], true)) return '';
        $data = $body['data'] ?? null;
        if (! is_array($data) || ! is_string($data['payment_session_id'] ?? null)) throw new \UnexpectedValueException;
        try { $this->session($data, $data['payment_session_id'] ?? ''); }
        catch (GatewayUnavailable $e) { throw new \UnexpectedValueException; }
        if ($data['status'] !== ($body['event'] === 'payment_session.completed' ? 'COMPLETED' : 'EXPIRED')) throw new \UnexpectedValueException;
        $model = $this->attemptModel();
        $attempt = $model::where('provider', 'xendit')->where(function ($query) use ($data) {
            $query->where('provider_session_id', $data['payment_session_id'])
                ->orWhere('reference', str_starts_with($data['reference_id'], 'FC-WATER-') ? substr($data['reference_id'], 9) : '');
        })->first();
        if (! $attempt) return '';
        if ($attempt->display_reference !== $data['reference_id'] || $attempt->amount_minor !== $this->minor($data['amount'])
            || ($attempt->provider_session_id && $attempt->provider_session_id !== $data['payment_session_id'])) throw new \UnexpectedValueException;
        // Authenticated API lookup remains mandatory; body amount/status never causes settlement.
        return $data['payment_session_id'];
    }
}
