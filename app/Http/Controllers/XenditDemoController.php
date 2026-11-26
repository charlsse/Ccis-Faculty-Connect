<?php
namespace App\Http\Controllers;

use App\Models\{WaterMember, XenditDemoAttempt};
use App\Services\Payments\{DemoXenditGateway, XenditDemoService, XenditGateway, GatewayUnavailable};
use Illuminate\Http\Request;

class XenditDemoController extends Controller
{
    public function __construct(private XenditDemoService $service) {}
    private function authorizeAttempt(Request $request, XenditDemoAttempt $attempt): void
    {
        abort_unless($attempt->source_database === config('xendit_demo.main_database') &&
            ($attempt->user_id === $request->user()->id || $request->user()->hasPermission('payments.view')), 403);
    }
    public function start(Request $request, WaterMember $member)
    {
        \Illuminate\Support\Facades\Gate::authorize('submit', $member);
        $request->validate(['test_mode_confirmed' => 'accepted']);
        try { $attempt = $this->service->initiate($member); }
        catch (GatewayUnavailable $e) { return back()->with('water_warning', $e->getMessage()); }
        if ($attempt->status === 'pending' && !$attempt->issue_code && XenditGateway::safeCheckoutUrl($attempt->checkout_url)) return redirect()->away($attempt->checkout_url);
        return redirect()->route('water.demo.show', $attempt);
    }
    public function index(Request $request)
    {
        return view('water.demo.index', ['transactions' => app(\App\Services\PaymentHistory::class)->forUser($request->user(), $request),
            'oversight' => $request->user()->hasPermission('payments.view')]);
    }
    public function show(Request $request, XenditDemoAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        return view('water.demo.show', compact('attempt'));
    }
    public function check(Request $request, XenditDemoAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        try { $this->service->check($attempt); }
        catch (GatewayUnavailable $e) { return back()->with('water_warning', 'Provider verification is temporarily unavailable. Your test payment remains unconfirmed.'); }
        return redirect()->route('water.demo.show', $attempt);
    }
    public function status(Request $request, XenditDemoAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        return response()->json(['status' => $attempt->status, 'receipt_url' => $attempt->status === 'confirmed'
            ? route('water.demo.receipt', $attempt) : null])->header('Cache-Control', 'private, no-store');
    }
    public function receipt(Request $request, XenditDemoAttempt $attempt)
    {
        $this->authorizeAttempt($request, $attempt);
        abort_unless($attempt->status === 'confirmed', 404);
        $faculty = \App\Models\User::findOrFail($attempt->user_id);
        if ($request->boolean('download')) {
            return response(app(\App\Services\WaterReceiptPdf::class)->renderDemo($attempt, $faculty), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$attempt->receipt_number.'.pdf"',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        return view('water.demo.receipt', compact('attempt', 'faculty'));
    }
    public function webhook(Request $request, DemoXenditGateway $gateway)
    {
        try {
            $id = $gateway->verifiedWebhookPaymentId($request);
            if ($id !== '') $this->service->reconcile($id);
        } catch (\UnexpectedValueException|\JsonException $e) {
            // Fixed boolean diagnostics only: never log payloads, identifiers or tokens.
            $body = json_decode($request->getContent(), true);
            $body = is_array($body) ? $body : [];
            $data = is_array($body['data'] ?? null) ? $body['data'] : [];
            $merchant = config('xendit_demo.credentials.business_id');
            \Illuminate\Support\Facades\Log::notice('Demo webhook rejected', [
                'reason' => 'invalid_event',
                'envelope_merchant_matches' => ($body['business_id'] ?? null) === $merchant,
                'nested_merchant_present' => array_key_exists('business_id', $data),
                'nested_merchant_matches' => ($data['business_id'] ?? null) === $merchant,
                'supported_event' => in_array($body['event'] ?? null, ['payment_session.completed', 'payment_session.expired'], true),
                'session_id_format_valid' => is_string($data['payment_session_id'] ?? null) && preg_match('/^ps-[a-f0-9]{24}$/D', $data['payment_session_id']) === 1,
                'session_id_type' => get_debug_type($data['payment_session_id'] ?? null),
                'session_id_length' => is_string($data['payment_session_id'] ?? null) ? strlen($data['payment_session_id']) : null,
                'alternate_id_type' => get_debug_type($data['id'] ?? null),
                'alternate_id_length' => is_string($data['id'] ?? null) ? strlen($data['id']) : null,
                'reference_length' => is_string($data['reference_id'] ?? null) ? strlen($data['reference_id']) : null,
                'session_id_has_controls' => is_string($data['payment_session_id'] ?? null) && preg_match('/[\x00-\x1f\x7f]/', $data['payment_session_id']) === 1,
                'reference_has_controls' => is_string($data['reference_id'] ?? null) && preg_match('/[\x00-\x1f\x7f]/', $data['reference_id']) === 1,
                'reference_is_string' => is_string($data['reference_id'] ?? null),
                'session_type_pay' => ($data['session_type'] ?? null) === 'PAY',
                'mode_payment_link' => ($data['mode'] ?? null) === 'PAYMENT_LINK',
                'currency_php' => ($data['currency'] ?? null) === 'PHP',
                'country_ph' => ($data['country'] ?? null) === 'PH',
                'amount_numeric' => is_int($data['amount'] ?? null) || is_float($data['amount'] ?? null),
                'status_matches_event' => isset($body['event']) && ($data['status'] ?? null) === ($body['event'] === 'payment_session.completed' ? 'COMPLETED' : 'EXPIRED'),
            ]);
            return response()->json(['error' => 'Invalid webhook.'], 400);
        } catch (GatewayUnavailable $e) {
            \Illuminate\Support\Facades\Log::notice('Demo webhook unavailable', ['reason' => config('xendit_demo.enabled') ? 'verification_unavailable' : 'demo_disabled']);
            return response()->json(['error' => 'Verification unavailable.'], 503);
        }
        \Illuminate\Support\Facades\Log::info('Demo webhook acknowledged', ['outcome' => $id === '' ? 'ignored_unmatched_or_unsupported_event' : 'provider_reconciled']);
        return response()->json(['received' => true]);
    }
    public function returnToMain(string $reference)
    {
        // Approved fixed origin; no redirect parameter or payment success assertion.
        $origin = config('presentation.origin') ?: 'http://127.0.0.1:8000';
        return redirect($origin.'/dashboard/water/demo/'.$reference)->header('Referrer-Policy', 'no-referrer');
    }
}
