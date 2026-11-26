<?php
namespace Tests\Feature;

use App\Models\{User, WaterCollection, WaterMember, WaterPaymentAccount, XenditDemoAttempt};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class XenditDemoTest extends TestCase
{
    use RefreshDatabase;
    private User $faculty;
    private WaterMember $member;
    private const BUSINESS = '661f87c614802d6c402cd82d';
    private const SESSION = 'ps-661f87c614802d6c402cd82d';
    private const REQUEST = 'pr-1102feb0-bb79-47ae-9d1e-e69394d3949c';
    private const PAYMENT = 'py-1102feb0-bb79-47ae-9d1e-e69394d3949c';
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.xendit_demo.database' => ':memory:', 'xendit_demo.enabled' => true,
            'xendit_demo.return_base' => 'https://demo.example.org', 'app.url' => 'http://127.0.0.1:8000',
            'payment_gateway.xendit.secret_key' => 'xnd_development_fixture',
            'payment_gateway.xendit.callback_token' => 'synthetic_callback_token',
            'payment_gateway.xendit.business_id' => self::BUSINESS,
            'payment_gateway.xendit.treasurer_account_confirmed' => true]);
        DB::purge('xendit_demo');
        (require database_path('demo/001_create_demo_ledger.php'))->up();
        (require database_path('demo/002_create_notification_outbox.php'))->up();
        $this->faculty = User::factory()->create();
        $treasurer = User::factory()->create(['role' => 'treasurer']);
        $account = WaterPaymentAccount::create(['label' => 'Test', 'account_name' => 'Test', 'masked_account_number' => '***1234', 'qr_image_path' => 'test.png', 'created_by' => $treasurer->id, 'is_active' => true]);
        $collection = WaterCollection::create(['title' => 'Demo contribution', 'period' => '2026-11', 'amount' => '500.00', 'due_date' => now()->addWeek(), 'status' => 'published', 'payment_account_id' => $account->id, 'created_by' => $treasurer->id]);
        $this->member = $collection->members()->create(['user_id' => $this->faculty->id, 'assigned_amount' => '500.00', 'assigned_at' => now(), 'status' => 'unpaid']);
        Http::preventStrayRequests();
        $this->fake();
        // Most tests focus on provider/ledger behavior; connectivity has its own tests.
        $gateway = \Mockery::mock(\App\Services\Payments\DemoXenditGateway::class)->makePartial();
        $gateway->shouldReceive('assertReturnAvailable')->andReturnNull();
        $this->app->instance(\App\Services\Payments\DemoXenditGateway::class, $gateway);
    }
    protected function tearDown(): void { DB::purge('xendit_demo'); parent::tearDown(); }
    private function providerSession(string $reference, string $status = 'ACTIVE'): array
    {
        return ['payment_session_id' => self::SESSION, 'reference_id' => $reference, 'session_type' => 'PAY', 'mode' => 'PAYMENT_LINK',
            'business_id' => self::BUSINESS, 'currency' => 'PHP', 'country' => 'PH', 'amount' => 500, 'status' => $status,
            'payment_link_url' => 'https://checkout-staging.xendit.co/sessions/'.self::SESSION,
            'payment_request_id' => self::REQUEST, 'payment_id' => self::PAYMENT];
    }
    private function fake(?array $session = null, int $capture = 500): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $payment = ['payment_request_id' => self::REQUEST, 'business_id' => self::BUSINESS, 'country' => 'PH', 'currency' => 'PHP', 'request_amount' => 500,
            'type' => 'PAY', 'capture_method' => 'AUTOMATIC', 'status' => 'SUCCEEDED', 'latest_payment_id' => self::PAYMENT];
        Http::fake(['api.xendit.co/sessions' => fn($r) => Http::response($this->providerSession($r['reference_id'])),
            'api.xendit.co/sessions/*' => Http::response($session ?? []),
            'api.xendit.co/v3/payment_requests/*' => Http::response($payment),
            'api.xendit.co/v3/payments/*' => Http::response($payment + ['payment_id' => self::PAYMENT,
                'captures' => [['capture_id' => 'opaque-demo-capture', 'capture_amount' => $capture, 'capture_timestamp' => now()->toIso8601String()]]])]);
    }
    private function start(): XenditDemoAttempt
    {
        $this->actingAs($this->faculty)->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1, 'amount' => 1, 'user_id' => 999])->assertRedirect('https://checkout-staging.xendit.co/sessions/'.self::SESSION);
        return XenditDemoAttempt::firstOrFail();
    }
    private function webhook(XenditDemoAttempt $attempt, string $token = 'synthetic_callback_token')
    {
        return $this->withHeaders(['x-callback-token' => $token])->postJson('/webhooks/payments/xendit-demo', [
            'event' => 'payment_session.completed', 'business_id' => self::BUSINESS, 'data' => $this->providerSession($attempt->display_reference, 'COMPLETED')]);
    }
    public function test_main_checkout_duplicate_clicks_and_authoritative_amount(): void
    {
        $checkout = $this->actingAs($this->faculty)->get(route('water.checkout', $this->member))->assertOk()->assertSee('Continue to Payment')->assertSee('Manual QR Payment')->assertDontSee('data-qr-dialog', false);
        $this->assertMatchesRegularExpression('/<details class="fc-manual-option"\s*>/', $checkout->getContent());
        $this->assertLessThan(strpos($checkout->getContent(), 'Manual QR Payment'), strpos($checkout->getContent(), 'Online Payment'));
        $attempt = $this->start();
        $this->assertSame(50000, $attempt->amount_minor);
        $this->assertSame($this->faculty->id, $attempt->user_id);
        $this->fake($this->providerSession($attempt->display_reference));
        $this->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect();
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
        $this->assertSame(1, XenditDemoAttempt::count());
        $this->assertFalse(app(\App\Services\Payments\WaterGatewayService::class)->available());
    }
    public function test_verified_webhook_is_idempotent_and_preserves_main_financial_tables(): void
    {
        $attempt = $this->start();
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name');
        $before = $tables->mapWithKeys(fn($t) => [$t => DB::table($t)->get()->toJson()])->all();
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'));
        $this->webhook($attempt)->assertOk();
        $this->webhook($attempt)->assertOk();
        $this->assertSame('confirmed', $attempt->fresh()->status);
        $this->assertSame(1, XenditDemoAttempt::count());
        $this->assertSame(2, \App\Models\WaterNotice::count());
        $this->assertSame(1, DB::connection('xendit_demo')->table('notification_outbox')->count());
        foreach ($before as $table => $rows) if ($table !== 'water_notices') $this->assertSame($rows, DB::table($table)->get()->toJson(), $table.' changed');
        $this->get(route('water.demo.show', $attempt))->assertOk()->assertSee('Payment Completed')->assertSee('actual FacultyConnect contribution remains unchanged');
        $this->get(route('water.demo.receipt', $attempt))->assertOk()->assertSee('DEMO-')->assertSee('No Funds Collected')->assertSee($this->faculty->full_name);
        $this->get(route('water.demo.index'))->assertOk()->assertSee('Demo contribution');
        $this->actingAs(User::where('role', 'treasurer')->first())->get(route('water.demo.index'))->assertOk()->assertSee('Transaction History')->assertSee('5'.'00.00');
    }
    public function test_cross_database_notification_failure_is_recoverable_without_duplicate_alerts(): void
    {
        $attempt = $this->start();
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'));
        $broken = \Mockery::mock(\App\Services\PaymentNotifications::class)->makePartial();
        $broken->shouldReceive('notice')->andThrow(new \RuntimeException('Unavailable notification database'));
        $this->app->instance(\App\Services\PaymentNotifications::class, $broken);
        $this->webhook($attempt)->assertOk();
        $this->assertSame('confirmed', $attempt->fresh()->status);
        $this->assertSame(0, \App\Models\WaterNotice::count());
        $this->assertNull(DB::connection('xendit_demo')->table('notification_outbox')->value('delivered_at'));
        $service = new \App\Services\PaymentNotifications;
        $this->assertSame(1, $service->deliverDemo());
        $this->assertSame(0, $service->deliverDemo());
        $this->assertSame(2, \App\Models\WaterNotice::count());
        $this->assertSame(0, \App\Models\WaterReceipt::count());
        $this->assertSame('unpaid', $this->member->fresh()->status);
        $this->actingAs($this->faculty)->get(route('notifications.feed'))->assertOk()->assertJsonPath('unread', 1)
            ->assertSee('No funds collected');
    }

    public function test_status_endpoint_is_authorized_read_only_and_terminal_receipt_is_unique(): void
    {
        $attempt = $this->start();
        $this->get(route('water.demo.status', $attempt))->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('receipt_url', null);
        Http::assertSentCount(1);
        $this->actingAs(User::factory()->create())->get(route('water.demo.status', $attempt))->assertForbidden();
        $this->actingAs($this->faculty);
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'));
        $this->webhook($attempt)->assertOk();
        $this->get(route('water.demo.status', $attempt))->assertOk()->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('receipt_url', route('water.demo.receipt', $attempt));
        $this->assertSame(1, XenditDemoAttempt::where('status', 'confirmed')->count());
        $this->assertSame(2, \App\Models\WaterNotice::count());
    }

    public function test_demo_rollback_does_not_emit_notifications(): void
    {
        $attempt = $this->start();
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'));
        DB::connection('xendit_demo')->beginTransaction();
        app(\App\Services\Payments\XenditDemoService::class)->reconcile(self::SESSION);
        $this->assertSame(0, \App\Models\WaterNotice::count());
        DB::connection('xendit_demo')->rollBack();
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame(0, DB::connection('xendit_demo')->table('notification_outbox')->count());
    }

    public function test_other_faculty_and_forged_webhook_cannot_access_or_confirm(): void
    {
        $attempt = $this->start();
        $this->webhook($attempt, 'wrong-token')->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->get(route('water.demo.show', $attempt))->assertForbidden();
        $this->get(route('water.demo.receipt', $attempt))->assertForbidden();
        $this->post(route('water.demo.check', $attempt))->assertForbidden();
        $this->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1])->assertForbidden();
        $this->get(route('water.demo.index'))->assertOk()->assertDontSee('Demo contribution');
        $this->assertSame('pending', $attempt->fresh()->status);
    }
    public function test_return_never_confirms_and_pending_receipt_is_unavailable(): void
    {
        $attempt = $this->start();
        $this->get('/payments/xendit/demo-return/'.$attempt->reference.'?status=success&redirect=https://evil.example')->assertRedirect('http://127.0.0.1:8000/dashboard/water/demo/'.$attempt->reference);
        $this->get(route('water.demo.show', $attempt))->assertOk()->assertSee('Payment Processing');
        $this->get(route('water.demo.receipt', $attempt))->assertNotFound();
        $this->assertSame('pending', $attempt->fresh()->status);
        Http::assertSentCount(1);
    }
    public function test_wrong_capture_amount_fails_closed(): void
    {
        $attempt = $this->start();
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'), 1);
        $this->webhook($attempt)->assertStatus(503);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('unpaid', $this->member->fresh()->status);
    }
    public function test_retry_replaces_provider_confirmed_expired_session(): void
    {
        $old = $this->start();
        $newId = 'ps-'.str_repeat('a', 24);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $expired = $this->providerSession($old->display_reference, 'EXPIRED');
        unset($expired['payment_id'], $expired['payment_request_id']);
        Http::fake([
            'api.xendit.co/sessions/'.self::SESSION => Http::response($expired),
            'api.xendit.co/sessions' => fn ($request) => Http::response(array_replace(
                $this->providerSession($request['reference_id']),
                ['payment_session_id' => $newId, 'payment_link_url' => 'https://checkout-staging.xendit.co/sessions/'.$newId]
            )),
        ]);
        $this->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1])
            ->assertRedirect('https://checkout-staging.xendit.co/sessions/'.$newId);
        $this->assertSame('expired', $old->fresh()->status);
        $this->assertNull($old->fresh()->active_member_id);
        $this->assertSame(2, XenditDemoAttempt::count());
        $this->assertSame('unpaid', $this->member->fresh()->status);
        Http::assertSentCount(2);
    }

    public function test_return_connectivity_rejects_offline_tunnel(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['https://demo.example.org/*' => Http::response('offline', 404)]);
        $this->expectException(\App\Services\Payments\GatewayUnavailable::class);
        (new \App\Services\Payments\DemoXenditGateway)->assertReturnAvailable();
    }

    public function test_expired_demo_with_unused_requires_action_request_releases_only_demo_reservation(): void
    {
        $attempt = $this->start();
        $session = $this->providerSession($attempt->display_reference, 'EXPIRED');
        unset($session['payment_id']);
        $payment = ['payment_request_id' => self::REQUEST, 'business_id' => self::BUSINESS,
            'currency' => 'PHP', 'country' => 'PH', 'type' => 'PAY', 'request_amount' => 500];
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake([
            'api.xendit.co/sessions/*' => Http::response($session),
            'api.xendit.co/v3/payment_requests/'.self::REQUEST => Http::response($payment + ['status' => 'REQUIRES_ACTION']),
        ]);
        app(\App\Services\Payments\XenditDemoService::class)->reconcile(self::SESSION);
        $this->assertSame('expired', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->active_member_id);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_offline_return_blocks_checkout_before_reserving_or_creating(): void
    {
        $this->app->instance(\App\Services\Payments\DemoXenditGateway::class, new \App\Services\Payments\DemoXenditGateway);
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['https://demo.example.org/*' => Http::response('offline', 404)]);
        $this->actingAs($this->faculty)->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1])
            ->assertRedirect()->assertSessionHas('water_warning');
        $this->assertSame(0, XenditDemoAttempt::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.xendit.co'));
    }

    public function test_verification_outage_does_not_reopen_old_checkout_or_create_another(): void
    {
        $old = $this->start();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['api.xendit.co/*' => Http::response([], 503)]);
        $this->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1])
            ->assertRedirect()->assertSessionHas('water_warning');
        $this->assertSame(1, XenditDemoAttempt::count());
        $this->assertSame('pending', $old->fresh()->status);
        Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_return_connectivity_accepts_only_expected_local_redirect(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['https://demo.example.org/*' => Http::response('', 303, [
            'Location' => 'http://127.0.0.1:8000/dashboard/water/demo/11111111-1111-4111-8111-111111111111',
        ])]);
        (new \App\Services\Payments\DemoXenditGateway)->assertReturnAvailable();
        Http::assertSentCount(1);
    }

    public function test_expired_session_releases_only_demo_reservation(): void
    {
        $attempt = $this->start();
        $session = $this->providerSession($attempt->display_reference, 'EXPIRED');
        unset($session['payment_id'], $session['payment_request_id']);
        $this->fake($session);
        $this->post(route('water.demo.check', $attempt))->assertRedirect();
        $this->assertSame('expired', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->active_member_id);
        $this->get(route('water.demo.show', $attempt))->assertOk()->assertSee('Payment Expired');
        $this->assertSame('unpaid', $this->member->fresh()->status);
    }
    public function test_demo_routes_keep_two_factor_middleware(): void
    {
        $this->actingAs($this->faculty)->withSession(['two_factor_user_id' => null])->get(route('water.demo.index'))->assertRedirect();
    }
    public function test_late_expiry_cannot_revert_verified_success(): void
    {
        $attempt = $this->start();
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'));
        $this->webhook($attempt)->assertOk();
        $session = $this->providerSession($attempt->display_reference, 'EXPIRED');
        unset($session['payment_id'], $session['payment_request_id']);
        $this->fake($session);
        $this->withHeaders(['x-callback-token' => 'synthetic_callback_token'])->postJson('/webhooks/payments/xendit-demo', [
            'event' => 'payment_session.expired', 'business_id' => self::BUSINESS, 'data' => $session])->assertOk();
        $this->assertSame('confirmed', $attempt->fresh()->status);
        $this->assertSame(self::PAYMENT, $attempt->fresh()->provider_payment_id);
    }
    public function test_lost_creation_response_reserves_attempt_and_is_not_retried(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['api.xendit.co/*' => Http::response([], 503)]);
        $this->actingAs($this->faculty)->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect();
        $this->post(route('water.demo.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect();
        Http::assertSentCount(1);
        $attempt = XenditDemoAttempt::firstOrFail();
        $this->assertSame('unknown', $attempt->status);
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'));
        $this->webhook($attempt)->assertOk();
        $this->assertSame('confirmed', $attempt->fresh()->status);
    }
    public function test_mismatched_webhook_amount_and_merchant_are_rejected(): void
    {
        $attempt = $this->start();
        $session = $this->providerSession($attempt->display_reference, 'COMPLETED');
        $session['amount'] = 1;
        $this->withHeaders(['x-callback-token' => 'synthetic_callback_token'])->postJson('/webhooks/payments/xendit-demo', [
            'event' => 'payment_session.completed', 'business_id' => self::BUSINESS, 'data' => $session])->assertStatus(400);
        $this->postJson('/webhooks/payments/xendit-demo', ['event' => 'payment_session.completed', 'business_id' => str_repeat('0',24), 'data' => $session])->assertStatus(400);
        $this->assertSame('pending', $attempt->fresh()->status);
        Http::assertSentCount(1);
    }
    public function test_disabled_demo_rejects_callbacks_before_provider_or_ledger_work(): void
    {
        config(['xendit_demo.enabled' => false]);
        $this->postJson('/webhooks/payments/xendit-demo', [])->assertStatus(503);
        Http::assertNothingSent();
        $this->assertSame(0, XenditDemoAttempt::count());
    }

    public function test_authenticated_unknown_samples_are_acknowledged_without_writes_or_provider_calls(): void
    {
        $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name');
        $before = $tables->mapWithKeys(fn($t) => [$t => DB::table($t)->get()->toJson()])->all();
        foreach (['COMPLETED' => 'payment_session.completed', 'EXPIRED' => 'payment_session.expired'] as $status => $event) {
            $body = ['event' => $event, 'business_id' => self::BUSINESS,
                'data' => $this->providerSession('FC-WATER-11111111-1111-4111-8111-111111111111', $status)];
            $this->withHeaders(['x-callback-token' => 'wrong-token'])->postJson('/webhooks/payments/xendit-demo', $body)->assertUnauthorized();
            $this->withHeaders(['x-callback-token' => 'synthetic_callback_token'])->postJson('/webhooks/payments/xendit-demo', $body)->assertOk();
            $this->postJson('/webhooks/payments/xendit-demo', $body)->assertOk();
            $body['data']['business_id'] = str_repeat('0', 24);
            $this->postJson('/webhooks/payments/xendit-demo', $body)->assertOk();
            // Dashboard placeholders are unrelated messages, never payment evidence.
            $body['business_id'] = 'sample-merchant';
            unset($body['data']['business_id']);
            $body['data']['payment_session_id'] = 'sample-session';
            $body['data']['reference_id'] = 'sample-reference';
            $body['data']['session_type'] = 'SAVE';
            $body['data']['currency'] = 'IDR';
            $body['data']['country'] = 'ID';
            $this->postJson('/webhooks/payments/xendit-demo', $body)->assertOk();
            $body['data']['id'] = $body['data']['payment_session_id'];
            unset($body['data']['payment_session_id']);
            $this->postJson('/webhooks/payments/xendit-demo', $body)->assertOk();
            $this->withHeaders(['x-callback-token' => 'wrong-token'])->postJson('/webhooks/payments/xendit-demo', $body)->assertUnauthorized();
        }
        Http::assertNothingSent();
        $this->assertSame(0, XenditDemoAttempt::count());
        foreach ($before as $table => $rows) $this->assertSame($rows, DB::table($table)->get()->toJson(), $table.' changed');
    }

    public function test_alternate_dashboard_id_cannot_hide_a_correlated_attempt(): void
    {
        $attempt = $this->start();
        $body = ['event' => 'payment_session.completed', 'business_id' => 'sample-merchant',
            'data' => ['id' => self::SESSION, 'payment_session_id' => 'unrelated-sample', 'reference_id' => 'sample-reference']];
        $this->withHeaders(['x-callback-token' => 'synthetic_callback_token'])->postJson('/webhooks/payments/xendit-demo', $body)->assertStatus(400);
        unset($body['data']['payment_session_id']);
        $this->postJson('/webhooks/payments/xendit-demo', $body)->assertStatus(400);
        $body['data']['id'] = 'sample-session';
        $body['data']['reference_id'] = $attempt->display_reference;
        $this->postJson('/webhooks/payments/xendit-demo', $body)->assertStatus(400);
        $this->assertSame('pending', $attempt->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_unified_history_filters_owner_scope_and_pagination_without_accounting_writes(): void
    {
        $attempt = $this->start();
        $submission = \App\Models\WaterSubmission::create(['water_member_id' => $this->member->id, 'attempt_number' => 1,
            'payment_method' => 'qr', 'reference_number' => 'MANUAL-123', 'submitted_amount' => 500,
            'paid_at' => now(), 'submitted_at' => now(), 'status' => 'pending']);
        $this->get(route('water.demo.index'))->assertOk()->assertSee('Online Payment')->assertSee('MANUAL-123')->assertSee('Simulation');
        $this->get(route('water.demo.index', ['method'=>'qr', 'q'=>'MANUAL-123', 'status'=>'pending', 'from'=>now()->format('Y-m-d')]))
            ->assertOk()->assertSee('MANUAL-123')->assertDontSee($attempt->display_reference);
        $this->get(route('water.demo.index', ['method'=>'online']))->assertOk()->assertSee($attempt->display_reference)->assertDontSee('MANUAL-123');
        $this->get(route('water.demo.index', ['to'=>now()->format('Y-m-d')]))->assertOk()->assertSee('MANUAL-123');
        for ($i=0; $i<21; $i++) {
            $copy = $attempt->replicate(); $copy->reference = (string)\Illuminate\Support\Str::uuid();
            $copy->active_member_id = null; $copy->provider_session_id = null; $copy->save();
        }
        $this->get(route('water.demo.index'))->assertOk()->assertViewHas('transactions', fn($p) => $p->total() === 23 && $p->count() === 20);
        $this->get(route('water.demo.index', ['page'=>2]))->assertOk()->assertViewHas('transactions', fn($p) => $p->count() === 3);
        $this->actingAs(User::factory()->create())->get(route('water.demo.index'))->assertOk()->assertViewHas('transactions', fn($p) => $p->total() === 0);
        $admin = User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->get(route('water.demo.index'))->assertOk()->assertViewHas('transactions', fn($p) => $p->total() === 23);
        $this->post(route('water.review', $submission), ['decision'=>'approve','confirmed'=>1])->assertForbidden();
        $this->assertSame('unpaid', $this->member->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_simulation_pdf_is_verified_owner_scoped_and_does_not_write_real_receipts(): void
    {
        $attempt = $this->start();
        $this->get(route('water.demo.receipt', [$attempt, 'download'=>1]))->assertNotFound();
        $this->fake($this->providerSession($attempt->display_reference, 'COMPLETED'));
        $this->webhook($attempt)->assertOk();
        $pdf = $this->get(route('water.demo.receipt', [$attempt, 'download'=>1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertDatabaseCount('water_receipts', 0);
        if (getenv('FC_UI_PREVIEWS') === '1') {
            $dir = storage_path('app/private/ui-preview');
            if (!is_dir($dir)) mkdir($dir, 0700, true);
            file_put_contents($dir.'/simulation-receipt.pdf', $pdf->getContent());
            file_put_contents($dir.'/simulation-receipt.html', $this->get(route('water.demo.receipt', $attempt))->getContent());
            file_put_contents($dir.'/checkout.html', $this->get(route('water.checkout', $this->member))->getContent());
            file_put_contents($dir.'/result.html', $this->get(route('water.demo.show', $attempt))->getContent());
            file_put_contents($dir.'/history.html', $this->get(route('water.demo.index'))->getContent());
        }
        $this->actingAs(User::factory()->create())->get(route('water.demo.receipt', [$attempt,'download'=>1]))->assertForbidden();
    }

    public function test_live_keys_wrong_database_and_wrong_port_disable_gateway(): void
    {
        $gateway = app(\App\Services\Payments\DemoXenditGateway::class);
        config(['payment_gateway.xendit.secret_key' => 'xnd_production_forbidden']);
        $this->assertFalse($gateway->available());
        config(['payment_gateway.xendit.secret_key' => 'xnd_development_fixture', 'database.default' => 'mysql', 'database.connections.mysql.port' => 3306]);
        $this->assertFalse($gateway->available());
        config(['database.connections.mysql.port' => 3307, 'database.connections.mysql.database' => 'wrong_database']);
        $this->assertFalse($gateway->available());
        config(['database.default' => 'sqlite']);
    }
}
