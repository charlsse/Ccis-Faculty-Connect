<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PayMongoGateway implements PaymentGatewayInterface
{
    public function available(): bool
    {
        return config('payment_gateway.enabled') === true
            && config('payment_gateway.provider') === 'paymongo'
            && config('payment_gateway.environment') === 'sandbox'
            && config('payment_gateway.sandbox_data_confirmed') === true
            && ! app()->environment('production')
            && str_starts_with((string) config('payment_gateway.secret_key'), 'sk_test_')
            && strlen((string) config('payment_gateway.webhook_secret')) >= 16;
    }

    private function client()
    {
        if (! $this->available()) throw new GatewayUnavailable('Online test payments are unavailable.');

        return Http::baseUrl('https://api.paymongo.com')->withBasicAuth(config('payment_gateway.secret_key'), '')
            ->acceptJson()->asJson()->connectTimeout(3)->timeout(10)->withoutRedirecting();
    }

    public static function safeCheckoutUrl(mixed $url): bool
    {
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) return false;
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'checkout.paymongo.com'
            && ! isset($parts['user']) && ! isset($parts['pass']) && (! isset($parts['port']) || $parts['port'] === 443);
    }

    public function createCheckout(string $reference, int $amountMinor, string $currency, string $returnUrl): array
    {
        try {
            $response = $this->client()->withHeaders(['Idempotency-Key' => $reference])->post('/v2/checkout_sessions', ['data' => ['attributes' => [
                'line_items' => [['name' => 'FacultyConnect water contribution (TEST)', 'amount' => $amountMinor, 'currency' => $currency, 'quantity' => 1]],
                'payment_method_types' => ['qrph'], 'reference_number' => $reference,
                'success_url' => $returnUrl, 'cancel_url' => $returnUrl,
                'send_email_receipt' => false, 'pass_on_fees' => false,
            ]]]);
            if (! $response->successful()) throw new GatewayUnavailable('Checkout creation was not confirmed.');
            $data = $response->json('data');
            if (! is_array($data) || ($data['type'] ?? null) !== 'checkout_session'
                || ! preg_match('/^cs_[A-Za-z0-9]+$/', $data['id'] ?? '')
                || ($data['attributes']['livemode'] ?? null) !== false
                || ! self::safeCheckoutUrl($data['attributes']['checkout_url'] ?? null)) {
                throw new GatewayUnavailable('Checkout response could not be verified.');
            }

            return ['payment_id' => $data['id'], 'checkout_url' => $data['attributes']['checkout_url']];
        } catch (\Throwable $e) {
            // Do not retain response bodies, authorization headers or exception chains.
            throw new GatewayUnavailable('Checkout creation was not confirmed.');
        }
    }

    public function retrievePayment(string $paymentId): array
    {
        if (! preg_match('/^cs_[A-Za-z0-9]+$/', $paymentId)) throw new GatewayUnavailable('Invalid checkout identifier.');
        try {
            $response = $this->client()->get('/v1/checkout_sessions/'.$paymentId);
            if (! $response->successful()) throw new GatewayUnavailable('Provider status unavailable.');
            $data = $response->json('data');
            $a = $data['attributes'] ?? [];
            if (($data['id'] ?? null) !== $paymentId || ($data['type'] ?? null) !== 'checkout_session' || ($a['livemode'] ?? null) !== false
                || ! is_string($a['reference_number'] ?? null) || ! is_array($a['line_items'] ?? null) || count($a['line_items']) !== 1) {
                throw new GatewayUnavailable('Provider response could not be verified.');
            }
            $item = $a['line_items'][0];
            if (! is_int($item['amount'] ?? null) || ($item['quantity'] ?? null) !== 1 || ! is_string($item['currency'] ?? null)) throw new GatewayUnavailable('Invalid amount.');
            $payments = $a['payments'] ?? null;
            if (! is_array($payments)) throw new GatewayUnavailable('Missing payment state.');
            $paid = array_values(array_filter($payments, fn ($p) => ($p['attributes']['status'] ?? '') === 'paid'));
            if (count($paid) > 1) throw new GatewayUnavailable('Multiple provider payments require investigation.');
            $result = ['payment_id' => $paymentId, 'reference' => $a['reference_number'], 'amount_minor' => $item['amount'],
                'currency' => $item['currency'], 'environment' => 'sandbox', 'status' => 'pending', 'paid_at' => null, 'charge_id' => null];
            if ($paid) {
                $p = $paid[0]; $pa = $p['attributes'];
                if (! preg_match('/^pay_[A-Za-z0-9]+$/', $p['id'] ?? '') || ($pa['livemode'] ?? null) !== false
                    || ($pa['amount'] ?? null) !== $item['amount'] || ($pa['currency'] ?? null) !== $item['currency']
                    || ($pa['source']['type'] ?? null) !== 'qrph' || ! is_int($pa['paid_at'] ?? null)
                    || $pa['paid_at'] <= 0 || $pa['paid_at'] > time() + 300) throw new GatewayUnavailable('Payment details mismatch.');
                $result['status'] = 'succeeded'; $result['charge_id'] = $p['id'];
                $result['paid_at'] = (new \DateTimeImmutable)->setTimestamp($pa['paid_at']);
            } elseif (($a['status'] ?? '') === 'expired') {
                // A failed individual payment is not a terminal checkout: it may be retried.
                // Release only an expired session with no in-flight/unknown payment statuses.
                $unresolved = array_filter($payments, fn ($p) => ! in_array($p['attributes']['status'] ?? '', ['failed'], true));
                $intent = $a['payment_intent']['attributes']['status'] ?? null;
                if (! $unresolved && in_array($intent, [null, 'cancelled'], true)) $result['status'] = 'expired';
            }

            return $result;
        } catch (\Throwable $e) {
            throw new GatewayUnavailable('Provider status could not be verified.');
        }
    }

    public function verifiedWebhookPaymentId(Request $request): string
    {
        if (! $this->available()) throw new GatewayUnavailable('Gateway disabled.');
        $raw = $request->getContent();
        if (strlen($raw) > 262144) throw new \UnexpectedValueException('Invalid event.');
        $parts = [];
        foreach (explode(',', $request->header('Paymongo-Signature', '')) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2 || isset($parts[$pair[0]])) throw new \UnexpectedValueException('Invalid signature.');
            $parts[$pair[0]] = $pair[1];
        }
        if (! ctype_digit($parts['t'] ?? '') || abs(time() - (int) $parts['t']) > 300
            || ! preg_match('/^[a-f0-9]{64}$/', $parts['te'] ?? '') || ! empty($parts['li'])
            || ! hash_equals(hash_hmac('sha256', $parts['t'].'.'.$raw, config('payment_gateway.webhook_secret')), $parts['te'])) {
            throw new \UnexpectedValueException('Invalid signature.');
        }
        $body = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        // Documented hosted-checkout envelope, plus the legacy Event resource envelope.
        $event = ($body['data']['type'] ?? '') === 'event' ? ($body['data']['attributes'] ?? []) : ($body['data'] ?? []);
        if (! is_array($event) || ($event['livemode'] ?? null) !== false) throw new \UnexpectedValueException('Not a test event.');
        if (($event['type'] ?? '') !== 'checkout_session.payment.paid') return '';
        $resource = $event['data'] ?? [];
        if (! is_array($resource) || ($resource['type'] ?? '') !== 'checkout_session' || ! is_string($resource['id'] ?? null) || ! preg_match('/^cs_[A-Za-z0-9]+$/', $resource['id'])) throw new \UnexpectedValueException('Invalid resource.');

        return $resource['id'];
    }
}
