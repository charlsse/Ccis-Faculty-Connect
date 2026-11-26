<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentGatewayInterface;
use App\Models\WaterGatewayAttempt;
use App\Models\WaterMember;
use App\Services\Payments\GatewayUnavailable;
use App\Services\Payments\PayMongoGateway;
use App\Services\Payments\XenditGateway;
use App\Services\Payments\WaterGatewayService;
use Illuminate\Http\Request;

class WaterGatewayController extends Controller
{
    public function __construct(private WaterGatewayService $payments) {}

    public function start(Request $request, WaterMember $member)
    {
        \Illuminate\Support\Facades\Gate::authorize('submit', $member);
        if ($this->payments->available()) $request->validate(['test_mode_confirmed' => 'accepted']);
        try { $attempt = $this->payments->initiate($member); }
        catch (GatewayUnavailable $e) { return back()->with('water_warning', $e->getMessage()); }
        if ($attempt->status === 'pending' && self::safeCheckout($attempt)) return redirect()->away($attempt->checkout_url);

        return redirect()->route('water.online.show', $attempt);
    }

    private function authorizeAttempt(Request $request, WaterGatewayAttempt $attempt): void
    {
        abort_unless($attempt->member->user_id === $request->user()->id || $request->user()->hasPermission('payments.view'), 403);
    }

    public static function safeCheckout(WaterGatewayAttempt $attempt): bool
    {
        return match ($attempt->provider) {
            'xendit' => XenditGateway::safeCheckoutUrl($attempt->checkout_url),
            'paymongo' => PayMongoGateway::safeCheckoutUrl($attempt->checkout_url),
            default => false,
        };
    }

    public function show(Request $request, WaterGatewayAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        $attempt->load('member.collection', 'member.receipt');

        // GET is display-only. No provider call or financial mutation on redirect.
        return view('water.online-status', compact('attempt'));
    }

    public function status(Request $request, WaterGatewayAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);

        return response()->json(['status' => $attempt->status, 'receipt_url' => $attempt->status === 'confirmed' && $attempt->member->receipt
            ? route('water.receipt', $attempt->member->receipt) : null])->header('Cache-Control', 'private, no-store');
    }

    public function check(Request $request, WaterGatewayAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        abort_unless($attempt->member->user_id === $request->user()->id || $request->user()->hasPermission('payments.manage'), 403);
        $this->payments->check($attempt);

        return redirect()->route('water.online.show', $attempt);
    }

    public function webhook(Request $request, PaymentGatewayInterface $gateway)
    {
        $provider = $request->is('webhooks/payments/xendit') ? 'xendit' : 'paymongo';
        abort_unless(config('payment_gateway.provider') === $provider, 404);
        try {
            $id = $gateway->verifiedWebhookPaymentId($request);
            if ($id !== '') $this->payments->reconcile($id);
        } catch (\UnexpectedValueException|\JsonException $e) {
            return response()->json(['error' => 'Invalid webhook.'], 400);
        } catch (GatewayUnavailable $e) {
            // Non-2xx asks the provider to retry; never acknowledge a lost confirmation.
            return response()->json(['error' => 'Confirmation temporarily unavailable.'], 503);
        }

        return response()->json(['received' => true]);
    }
}
