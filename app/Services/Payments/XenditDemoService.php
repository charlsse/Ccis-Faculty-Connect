<?php
namespace App\Services\Payments;

use App\Models\{WaterMember, XenditDemoAttempt};
use App\Services\WaterPaymentService;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Support\Str;

class XenditDemoService
{
    public function __construct(private DemoXenditGateway $gateway) {}
    public function ready(): bool
    {
        if (!$this->gateway->available()) return false;
        try { return Schema::connection('xendit_demo')->hasTable('demo_attempts'); }
        catch (\Throwable $e) { return false; }
    }
    public function initiate(WaterMember $member): XenditDemoAttempt
    {
        Gate::authorize('submit', $member);
        if (!$this->ready()) throw new GatewayUnavailable('Xendit demonstration checkout is not activated yet.');
        abort_unless($member->user_id === auth()->id(), 403);
        $amount = WaterPaymentService::cents($member->assigned_amount);
        if ($amount < 100 || $amount > 1000000000) throw new GatewayUnavailable('Test amount must be between PHP 1.00 and PHP 10,000,000.00.');
        $this->gateway->assertReturnAvailable();
        // A missed expiry webhook must never leave an old checkout reserved forever.
        $existing = XenditDemoAttempt::where('active_member_id', $member->id)->first();
        if ($existing && $existing->provider_session_id && $existing->status === 'pending') {
            $this->reconcile($existing->provider_session_id);
            $existing->refresh();
            if ($existing->status !== 'expired') return $existing;
        }
        [$attempt, $created] = DB::connection('xendit_demo')->transaction(function () use ($member, $amount) {
            $existing = XenditDemoAttempt::where('active_member_id', $member->id)->first();
            if ($existing) return [$existing, false];
            return [XenditDemoAttempt::create([
                'reference' => (string) Str::uuid(), 'source_database' => config('xendit_demo.main_database'),
                'user_id' => $member->user_id, 'water_member_id' => $member->id, 'active_member_id' => $member->id,
                'contribution_title' => $member->collection->title, 'billing_period' => $member->collection->period,
                'amount_minor' => $amount, 'status' => 'creating',
            ]), true];
        }, 3);
        if (!$created) return $attempt;
        try {
            $url = rtrim(config('xendit_demo.return_base'), '/').'/payments/xendit/demo-return/'.$attempt->reference;
            $checkout = $this->gateway->createCheckout($attempt->display_reference, $amount, 'PHP', $url);
            DB::connection('xendit_demo')->transaction(function () use ($attempt, $checkout) {
                $locked = XenditDemoAttempt::findOrFail($attempt->id);
                if ($locked->provider_session_id && $locked->provider_session_id !== $checkout['payment_id']) throw new GatewayUnavailable('Session mismatch.');
                if (in_array($locked->status, ['creating', 'unknown'], true)) $locked->update([
                    'provider_session_id' => $checkout['payment_id'], 'checkout_url' => $checkout['checkout_url'], 'status' => 'pending',
                ]);
            }, 3);
        } catch (GatewayUnavailable $e) {
            XenditDemoAttempt::whereKey($attempt->id)->where('status', 'creating')->update(['status' => 'unknown', 'issue_code' => 'creation_unconfirmed']);
        }
        return $attempt->fresh();
    }
    public function check(XenditDemoAttempt $attempt): void
    {
        if (!$attempt->provider_session_id || $attempt->status === 'confirmed') return;
        $claimed = XenditDemoAttempt::whereKey($attempt->id)->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<=', now()->subSeconds(15)))->update(['last_checked_at' => now()]);
        if ($claimed) $this->reconcile($attempt->provider_session_id);
    }
    public function reconcile(string $session): void
    {
        if (!$this->ready()) throw new GatewayUnavailable('Demo ledger unavailable.');
        $result = $this->gateway->retrievePayment($session);
        $reference = str_starts_with($result['reference'], 'FC-WATER-') ? substr($result['reference'], 9) : '';
        DB::connection('xendit_demo')->transaction(function () use ($session, $result, $reference) {
            $attempt = XenditDemoAttempt::where('reference', $reference)->lockForUpdate()->first();
            if (!$attempt) throw new GatewayUnavailable('Unknown demonstration reference.');
            if ($attempt->status === 'confirmed') {
                app(\App\Services\PaymentNotifications::class)->demoEvent($attempt);
                return;
            }
            if ($attempt->source_database !== config('xendit_demo.main_database') || $attempt->environment !== 'sandbox'
                || $attempt->provider !== 'xendit' || $result['environment'] !== 'sandbox'
                || $attempt->amount_minor !== $result['amount_minor'] || $result['currency'] !== $attempt->currency
                || $result['payment_id'] !== $session || ($attempt->provider_session_id && $attempt->provider_session_id !== $session)) {
                throw new GatewayUnavailable('Demo payment identity mismatch.');
            }
            $attempt->provider_session_id = $session;
            $attempt->last_checked_at = now();
            $attempt->issue_code = !empty($result['checkout_expired']) && $result['status'] === 'pending' ? 'expired_checkout_unresolved' : null;
            if ($result['status'] === 'succeeded') {
                if (!$result['charge_id'] || !$result['paid_at']) throw new GatewayUnavailable('Capture evidence missing.');
                $attempt->fill(['status' => 'confirmed', 'provider_payment_id' => $result['charge_id'],
                    'paid_at' => $result['paid_at'], 'confirmed_at' => now(), 'active_member_id' => null, 'issue_code' => null,
                    'payment_method' => $result['payment_method'] ?? 'Xendit Test Mode']);
            } elseif ($result['status'] === 'expired') {
                $attempt->fill(['status' => 'expired', 'active_member_id' => null]);
            } elseif (in_array($attempt->status, ['creating', 'unknown'], true)) {
                $attempt->status = 'pending';
            }
            $attempt->save();
            app(\App\Services\PaymentNotifications::class)->demoEvent($attempt);
        }, 3);
        // The durable source event survives a failed main-database write and is retried by maintenance.
        DB::connection('xendit_demo')->afterCommit(function () {
            try { app(\App\Services\PaymentNotifications::class)->deliverDemo(); }
            catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Demo notification delivery deferred', ['exception_type' => get_class($e)]); }
        });
    }
}
