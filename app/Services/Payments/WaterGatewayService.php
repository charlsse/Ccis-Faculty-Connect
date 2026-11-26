<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\WaterCollection;
use App\Models\WaterGatewayAttempt;
use App\Models\WaterMember;
use App\Models\WaterReceipt;
use App\Models\WaterSubmission;
use App\Services\WaterPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WaterGatewayService
{
    public function __construct(private PaymentGatewayInterface $gateway, private WaterPaymentService $payments) {}

    public function available(): bool
    {
        return $this->gateway->available() && Schema::hasTable('water_gateway_attempts');
    }

    public static function active(WaterMember $member): ?WaterGatewayAttempt
    {
        return Schema::hasTable('water_gateway_attempts')
            ? WaterGatewayAttempt::where(fn ($q) => $q->where('active_member_id', $member->id)
                ->orWhere(fn ($q) => $q->where('water_member_id', $member->id)->where('status', 'attention')))->latest('id')->first() : null;
    }

    public static function requireManualAvailable(WaterMember $member): void
    {
        // Called after acquiring the member lock; holds across the manual write.
        app(WaterPaymentService::class)->require(! self::active($member), 'An online payment is unresolved. Check its status before paying or recording another payment.');
    }

    public function initiate(WaterMember $member): WaterGatewayAttempt
    {
        Gate::authorize('submit', $member);
        if (config('payment_gateway.provider') === 'xendit') abort_unless(auth()->user()?->role === 'faculty', 403);
        if (! $this->available()) throw new GatewayUnavailable('Online test payments are unavailable. You can pay manually.');
        [$attempt, $created] = DB::transaction(function () use ($member) {
            $collection = WaterCollection::whereKey($member->water_collection_id)->lockForUpdate()->firstOrFail();
            $member = WaterMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->payments->require($collection->status === 'published' && in_array($member->status, ['unpaid', 'rejected'], true), 'This contribution is not available for online payment.');
            if ($existing = self::active($member)) return [$existing, false];
            $amount = WaterPaymentService::cents($member->assigned_amount);
            $this->payments->require($amount >= 100, 'Online QR Ph payments require at least PHP 1.00. Use manual payment.');
            $attempt = WaterGatewayAttempt::create(['reference' => (string) Str::uuid(), 'water_member_id' => $member->id,
                'active_member_id' => $member->id, 'amount_minor' => $amount, 'currency' => 'PHP', 'provider' => config('payment_gateway.provider'), 'environment' => 'sandbox', 'status' => 'creating']);
            $this->payments->audit('gateway_initiated', $member, ['attempt_id' => $attempt->id, 'environment' => 'sandbox']);

            return [$attempt, true];
        }, 3);
        if (! $created) return $attempt;
        try {
            // Only one outbound creation. A lost response leaves a reservation for reconciliation.
            $returnUrl = $attempt->provider === 'xendit' ? rtrim(config('app.url'), '/').'/payments/xendit/return' : route('water.online.show', $attempt);
            $checkout = $this->gateway->createCheckout($attempt->display_reference, $attempt->amount_minor, $attempt->currency, $returnUrl);
            DB::transaction(function () use ($attempt, $checkout) {
                WaterMember::whereKey($attempt->water_member_id)->lockForUpdate()->firstOrFail();
                $locked = WaterGatewayAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
                if ($locked->provider_session_id && $locked->provider_session_id !== $checkout['payment_id']) throw new GatewayUnavailable('Checkout identity mismatch.');
                if (in_array($locked->status, ['creating', 'unknown'], true)) {
                    $locked->update(['provider_session_id' => $checkout['payment_id'], 'checkout_url' => $checkout['checkout_url'], 'status' => 'pending', 'issue_code' => null]);
                }
            }, 3);
        } catch (GatewayUnavailable $e) {
            WaterGatewayAttempt::whereKey($attempt->id)->where('status', 'creating')->update(['status' => 'unknown', 'issue_code' => 'creation_unconfirmed']);
            $this->payments->audit('gateway_creation_unknown', $member, ['attempt_id' => $attempt->id]);
        }

        return $attempt->fresh();
    }

    public function check(WaterGatewayAttempt $attempt): WaterGatewayAttempt
    {
        if (! $this->available() || $attempt->provider !== config('payment_gateway.provider') || ! $attempt->provider_session_id || $attempt->status === 'confirmed') return $attempt;
        // A persisted cooldown works across browsers and PHP workers.
        $claimed = WaterGatewayAttempt::whereKey($attempt->id)->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subSeconds(15)))
            ->update(['last_checked_at' => now()]);
        if (! $claimed) return $attempt->fresh();
        try { $this->reconcile($attempt->provider_session_id); }
        catch (GatewayUnavailable $e) { /* Unknown stays unresolved; never infer failure from connectivity. */ }

        return $attempt->fresh();
    }

    public function reconcile(string $sessionId): ?WaterGatewayAttempt
    {
        if (! $this->available()) throw new GatewayUnavailable('Online payments are disabled.');
        $existing = WaterGatewayAttempt::where('provider', config('payment_gateway.provider'))->where('provider_session_id', $sessionId)->first();
        if ($existing?->status === 'confirmed') return $existing;
        $result = $this->gateway->retrievePayment($sessionId);
        $reference = str_starts_with($result['reference'], 'FC-WATER-') ? substr($result['reference'], 9) : '';
        $attempt = $existing ?? WaterGatewayAttempt::where('provider', config('payment_gateway.provider'))->where('reference', $reference)->first();
        if (! $attempt) return null;

        return DB::transaction(function () use ($attempt, $result, $sessionId) {
            $member = WaterMember::whereKey($attempt->water_member_id)->firstOrFail();
            WaterCollection::whereKey($member->water_collection_id)->lockForUpdate()->firstOrFail();
            $member = WaterMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $attempt = WaterGatewayAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status === 'confirmed') return $attempt;
            if ($result['payment_id'] !== $sessionId || ($attempt->provider_session_id && $attempt->provider_session_id !== $sessionId)
                || $result['reference'] !== $attempt->display_reference || $result['amount_minor'] !== $attempt->amount_minor
                || $result['currency'] !== 'PHP' || $attempt->currency !== 'PHP' || $result['environment'] !== 'sandbox'
                || $attempt->environment !== 'sandbox' || $attempt->provider !== config('payment_gateway.provider')
                || WaterPaymentService::cents($member->assigned_amount) !== $attempt->amount_minor) {
                return $this->attention($attempt, $member, 'identity_or_amount_mismatch');
            }
            $attempt->provider_session_id = $sessionId;
            $attempt->last_checked_at = now();
            if ($result['status'] === 'succeeded') {
                $other = self::active($member);
                if (! in_array($member->status, ['unpaid', 'rejected'], true) || $member->receipt()->exists()
                    || ($other && $other->id !== $attempt->id)) {
                    return $this->attention($attempt, $member, 'settlement_conflict');
                }
                if (WaterGatewayAttempt::where('provider_payment_id', $result['charge_id'])->where('id', '!=', $attempt->id)->exists()) {
                    return $this->attention($attempt, $member, 'duplicate_provider_payment');
                }
                $previous = $member->submissions()->first();
                $paidAt = \Illuminate\Support\Carbon::instance($result['paid_at'])->setTimezone(config('app.timezone'));
                $submission = WaterSubmission::create(['water_member_id' => $member->id, 'attempt_number' => ($previous?->attempt_number ?? 0) + 1,
                    'previous_submission_id' => $previous?->id, 'payment_method' => $attempt->provider.'_test', 'reference_number' => $attempt->display_reference,
                    'submitted_amount' => $member->assigned_amount, 'paid_at' => $paidAt, 'status' => 'approved', 'submitted_at' => now(), 'verified_at' => now(), 'verified_by' => null]);
                $member->update(['status' => 'paid', 'paid_at' => $paidAt]);
                $this->payments->receipt($member, $submission);
                $attempt->fill(['status' => 'confirmed', 'provider_payment_id' => $result['charge_id'], 'confirmed_at' => now(),
                    'water_submission_id' => $submission->id, 'active_member_id' => null, 'issue_code' => null])->save();
                $this->payments->audit('gateway_confirmed', $member, ['attempt_id' => $attempt->id, 'submission_id' => $submission->id, 'environment' => 'sandbox']);
                $this->payments->notify($member->user, $member->collection, 'TEST payment confirmed for '.$member->collection->period.'. Your sandbox receipt is ready. Not valid for real contribution dues.');
                foreach (app(\App\Services\PaymentNotifications::class)->treasurers() as $treasurer) {
                    $this->payments->notify($treasurer, $member->collection, 'A sandbox payment was verified. No actual funds collected. View the isolated test transaction.',
                        'gateway:'.$attempt->id.':confirmed', ['title' => 'Test Payment Verified', 'route' => 'water.online.show', 'parameters' => [$attempt->id], 'simulation' => true, 'required_permission' => 'payments.manage']);
                }
            } elseif ($result['status'] === 'expired') {
                if ($attempt->status !== 'attention') {
                    $attempt->fill(['status' => 'expired', 'active_member_id' => null, 'issue_code' => null]);
                    $this->payments->audit('gateway_expired', $member, ['attempt_id' => $attempt->id]);
                    $this->payments->notify($member->user, $member->collection, 'Your test checkout expired. View the transaction for permitted retry options. No funds collected.',
                        'gateway:'.$attempt->id.':expired', ['title' => 'Test Payment Expired', 'route' => 'water.online.show', 'parameters' => [$attempt->id], 'simulation' => true]);
                }
                $attempt->save();
            } else {
                if (in_array($attempt->status, ['creating', 'unknown'], true)) $attempt->fill(['status' => 'pending', 'issue_code' => null]);
                $attempt->save();
            }

            return $attempt;
        }, 3);
    }

    private function attention(WaterGatewayAttempt $attempt, WaterMember $member, string $reason): WaterGatewayAttempt
    {
        if ($attempt->status !== 'attention' || $attempt->issue_code !== $reason) {
            $attempt->fill(['status' => 'attention', 'issue_code' => $reason])->save();
            $this->payments->audit('gateway_attention', $member, ['attempt_id' => $attempt->id, 'issue' => $reason]);
        }

        return $attempt;
    }
}
