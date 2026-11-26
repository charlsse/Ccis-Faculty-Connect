<?php

namespace App\Contracts;

use Illuminate\Http\Request;

/**
 * Provider boundary for optional sandbox payments.
 *
 * Implementations must use the selected provider's official sandbox API and
 * documented authentication. They must not write contribution or receipt rows.
 * The application service must independently compare every returned identifier,
 * reference, amount, currency and environment against its locked local attempt.
 */
interface PaymentGatewayInterface
{
    public function available(): bool;

    /**
     * Amount and reference come from the persisted, authorized local attempt,
     * never browser input. A timeout is an unknown outcome, not permission to
     * issue another charge. Provider retry/idempotency rules belong in the adapter.
     *
     * @return array{payment_id: string, checkout_url: string}
     */
    public function createCheckout(string $reference, int $amountMinor, string $currency, string $returnUrl): array;

    /**
     * Perform an authenticated server-to-server lookup. Status normalization
     * must follow official provider documentation; unknown values stay unknown.
     * No browser redirect parameter may be used as evidence of success.
     *
     * @return array{
     *   payment_id: string,
     *   reference: string,
     *   amount_minor: int,
     *   currency: string,
     *   environment: 'sandbox',
     *   status: 'pending'|'succeeded'|'failed'|'cancelled'|'expired'|'unknown',
     *   paid_at: ?\DateTimeImmutable,
     *   charge_id: ?string
     * }
     */
    public function retrievePayment(string $paymentId): array;

    /**
     * Verify a webhook using the provider's documented mechanism, or reject it.
     * Return only a transaction identifier for authenticated reconciliation.
     * This method's result alone must never mark a contribution paid.
     * Do not invent a shared signature algorithm or log raw payloads/secrets.
     *
     * @throws \UnexpectedValueException When authenticity cannot be established.
     */
    public function verifiedWebhookPaymentId(Request $request): string;
}
