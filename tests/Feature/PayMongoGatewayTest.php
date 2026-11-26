<?php

namespace Tests\Feature;

use App\Models\{User, WaterCollection, WaterGatewayAttempt, WaterMember, WaterNotice, WaterPaymentAccount, WaterReceipt, WaterSubmission};
use App\Services\Payments\{PayMongoGateway, WaterGatewayService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class PayMongoGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $faculty;
    private User $officer;
    private WaterMember $member;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--path' => 'database/experimental/paymongo', '--force' => true]);
        Http::preventStrayRequests();
        Storage::fake('local');
        config(['water.email_enabled' => false, 'payment_gateway.enabled' => true, 'payment_gateway.provider' => 'paymongo',
            'payment_gateway.environment' => 'sandbox', 'payment_gateway.sandbox_data_confirmed' => true,
            'payment_gateway.secret_key' => 'sk_test_synthetic_not_a_real_key', 'payment_gateway.webhook_secret' => 'synthetic_webhook_secret_for_tests']);
        $this->faculty = User::factory()->create();
        $this->officer = User::factory()->create(['role' => 'treasurer']);
        $account = WaterPaymentAccount::create(['label' => 'Synthetic', 'account_name' => 'Synthetic club', 'masked_account_number' => '***1234', 'qr_image_path' => 'synthetic.png', 'created_by' => $this->officer->id, 'is_active' => true]);
        $collection = WaterCollection::create(['title' => 'Synthetic gateway contribution', 'period' => now()->format('Y-m'), 'amount' => '500.00', 'due_date' => now()->addWeek(), 'status' => 'published', 'published_at' => now(), 'payment_account_id' => $account->id, 'created_by' => $this->officer->id]);
        $this->member = $collection->members()->create(['user_id' => $this->faculty->id, 'assigned_amount' => '500.00', 'assigned_at' => now(), 'status' => 'unpaid']);
    }

    private function creation(string $id = 'cs_testOne'): array
    {
        return ['data' => ['id' => $id, 'type' => 'checkout_session', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/'.$id, 'livemode' => false]]];
    }

    private function fake($responses): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    private function start(): WaterGatewayAttempt
    {
        $this->fake(['api.paymongo.com/v2/checkout_sessions' => Http::response($this->creation())]);
        $this->actingAs($this->faculty)->post(route('water.online.start', $this->member), ['amount' => 1, 'test_mode_confirmed' => 1])->assertRedirect('https://checkout.paymongo.com/cs_testOne');

        return WaterGatewayAttempt::firstOrFail();
    }

    private function provider(WaterGatewayAttempt $attempt, bool $paid = true): array
    {
        return ['data' => ['id' => $attempt->provider_session_id ?? 'cs_testOne', 'type' => 'checkout_session', 'attributes' => [
            'livemode' => false, 'reference_number' => $attempt->display_reference, 'status' => 'active',
            'line_items' => [['amount' => 50000, 'currency' => 'PHP', 'quantity' => 1]],
            'payments' => $paid ? [['id' => 'pay_testOne', 'type' => 'payment', 'attributes' => ['amount' => 50000, 'currency' => 'PHP', 'livemode' => false, 'status' => 'paid', 'source' => ['type' => 'qrph'], 'paid_at' => now()->timestamp]]] : [],
        ]]];
    }

    private function webhook(string $id = 'cs_testOne', array $overrides = [], ?int $timestamp = null, bool $valid = true)
    {
        $raw = json_encode(['event_type' => 'send.webhook', 'data' => array_replace(['type' => 'checkout_session.payment.paid', 'livemode' => false,
            'data' => ['id' => $id, 'type' => 'checkout_session']], $overrides)]);
        $time = $timestamp ?? time();
        $signature = hash_hmac('sha256', $time.'.'.$raw, $valid ? config('payment_gateway.webhook_secret') : 'wrong_secret');

        return $this->call('POST', route('water.gateway.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => 't='.$time.',te='.$signature.',li='], $raw);
    }

    private function manual(): array
    {
        return ['submitted_amount' => '500.00', 'reference_number' => 'MANUAL-SYNTHETIC', 'payment_date' => now()->subDay()->toDateString(), 'payment_time' => '10:00', 'confirmed' => 1,
            'proof' => UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='))];
    }

    public function test_only_assigned_owner_can_initiate(): void
    {
        $this->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect(route('login'));
        foreach ([$this->officer, User::factory()->create()] as $other) $this->actingAs($other)->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertForbidden();
        $this->assertDatabaseCount('water_gateway_attempts', 0); Http::assertNothingSent();
    }

    public function test_server_amount_and_single_outbound_creation_on_double_click(): void
    {
        $attempt = $this->start();
        $this->assertSame(50000, $attempt->amount_minor); $this->assertSame('pending', $attempt->status);
        $this->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect('https://checkout.paymongo.com/cs_testOne');
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['data']['attributes']['line_items'][0]['amount'] === 50000 && $r['data']['attributes']['payment_method_types'] === ['qrph']
            && $r->hasHeader('Idempotency-Key', $attempt->display_reference));
        $this->assertDatabaseCount('water_gateway_attempts', 1); $this->assertSame('unpaid', $this->member->fresh()->status);
    }

    public function test_return_refresh_and_poll_never_confirm_payment(): void
    {
        $attempt = $this->start();
        $this->get(route('water.online.show', [$attempt, 'success' => 1, 'status' => 'paid']))->assertOk()->assertSee('Payment Processing');
        $this->get(route('water.online.show', $attempt))->assertOk();
        $this->get(route('water.online.status', $attempt))->assertOk()->assertJsonPath('status', 'pending');
        $this->assertDatabaseCount('water_receipts', 0); Http::assertSentCount(1);
    }

    public function test_forged_stale_live_and_unknown_events_do_not_pay(): void
    {
        $attempt = $this->start();
        $this->webhook(valid: false)->assertStatus(400);
        $this->webhook(timestamp: time() - 600)->assertStatus(400);
        $this->webhook(overrides: ['livemode' => true])->assertStatus(400);
        $this->webhook(overrides: ['type' => 'payment.failed'])->assertOk();
        $unknown = $this->provider($attempt); $unknown['data']['id'] = 'cs_unknown'; $unknown['data']['attributes']['reference_number'] = 'FC-WATER-unknown';
        $this->fake(['api.paymongo.com/v1/checkout_sessions/cs_unknown' => Http::response($unknown)]);
        $this->webhook('cs_unknown')->assertOk(); $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_verified_webhook_is_idempotent_and_updates_existing_reporting(): void
    {
        $attempt = $this->start(); $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response($this->provider($attempt))]);
        for ($i = 0; $i < 3; $i++) $this->webhook()->assertOk();
        $this->assertSame('paid', $this->member->fresh()->status);
        $this->assertSame('confirmed', $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->active_member_id);
        $this->assertDatabaseCount('water_receipts', 1); $this->assertDatabaseCount('water_submissions', 1); $this->assertDatabaseCount('water_notices', 2);
        $receipt = WaterReceipt::firstOrFail(); $this->assertNull($receipt->verified_by);
        $this->get(route('water.receipt', $receipt))->assertOk()->assertSee('No Funds Collected');
        $this->get(route('water.submission', WaterSubmission::first()))->assertOk()->assertSee('Online Payment');
        $this->actingAs($this->officer)->get(route('water.report', $this->member->collection))->assertOk()->assertViewHas('summary', fn ($s) => (float) $s['collected'] === 500.0);
        $csv = $this->get(route('water.report', [$this->member->collection, 'download' => 1]))->streamedContent();
        $this->assertStringContainsString('paymongo_test', $csv); $this->assertStringContainsString('Automatic - PayMongo TEST', $csv);
        $this->actingAs($this->faculty)->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertSessionHasErrors('payment');
    }

    public function test_amount_currency_reference_and_mode_mismatches_never_confirm(): void
    {
        $attempt = $this->start();
        foreach (['amount', 'currency', 'reference', 'livemode', 'payment_amount'] as $change) {
            $payload = $this->provider($attempt);
            $a = &$payload['data']['attributes'];
            if ($change === 'amount') { $a['line_items'][0]['amount'] = 100; $a['payments'][0]['attributes']['amount'] = 100; }
            if ($change === 'currency') { $a['line_items'][0]['currency'] = 'USD'; $a['payments'][0]['attributes']['currency'] = 'USD'; }
            if ($change === 'reference') $a['reference_number'] = 'FC-WATER-another';
            if ($change === 'livemode') $a['livemode'] = true;
            if ($change === 'payment_amount') $a['payments'][0]['attributes']['amount'] = 100;
            $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response($payload)]);
            $response = $this->webhook(); $this->assertContains($response->status(), [200, 503]);
            $this->assertDatabaseCount('water_receipts', 0); $this->assertSame('unpaid', $this->member->fresh()->status);
        }
    }

    public function test_disabled_missing_or_live_configuration_keeps_manual_flow_available(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        foreach ([['payment_gateway.enabled' => false], ['payment_gateway.secret_key' => null], ['payment_gateway.secret_key' => 'sk_live_never_use'],
            ['payment_gateway.sandbox_data_confirmed' => false], ['payment_gateway.environment' => 'production'], ['payment_gateway.webhook_secret' => null]] as $settings) {
            $before = config('payment_gateway'); config($settings);
            $this->actingAs($this->faculty)->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect()->assertSessionHas('water_warning');
            config(['payment_gateway' => $before]);
        }
        $this->assertDatabaseCount('water_gateway_attempts', 0); Http::assertNothingSent();
        config(['payment_gateway.enabled' => false]);
        $this->post(route('water.submit', $this->member), $this->manual())->assertSessionHasNoErrors();
        $submission = WaterSubmission::firstOrFail();
        $this->actingAs($this->officer)->post(route('water.review', $submission), ['decision' => 'approve', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('paid', $this->member->fresh()->status);
    }

    public function test_timeout_and_invalid_creation_response_reserve_unknown_attempt(): void
    {
        $this->actingAs($this->faculty);
        $this->fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('synthetic timeout'));
        $this->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect();
        $attempt = WaterGatewayAttempt::firstOrFail(); $this->assertSame('unknown', $attempt->status);
        $this->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect(route('water.online.show', $attempt));
        $this->assertDatabaseCount('water_gateway_attempts', 1);
        $this->post(route('water.submit', $this->member), $this->manual())->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_invalid_checkout_host_and_bad_gateway_responses_are_not_redirected(): void
    {
        $data = $this->creation(); $data['data']['attributes']['checkout_url'] = 'https://checkout.paymongo.com.evil.example/pay';
        $this->fake(['*' => Http::response($data)]);
        $this->actingAs($this->faculty)->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect();
        $this->assertSame('unknown', WaterGatewayAttempt::first()->status);
        $this->assertFalse(PayMongoGateway::safeCheckoutUrl('https://evil@checkout.paymongo.com/test'));
        $this->assertFalse(PayMongoGateway::safeCheckoutUrl('javascript:alert(1)'));
    }

    public function test_webhook_retrieval_outage_preserves_state_and_requests_redelivery(): void
    {
        $attempt = $this->start(); $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response([], 503)]);
        $this->webhook()->assertStatus(503); $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 0);
        $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response($this->provider($attempt))]);
        $this->webhook()->assertOk(); $this->assertDatabaseCount('water_receipts', 1);
    }

    public function test_failed_payment_is_not_success_and_active_checkout_is_not_released(): void
    {
        $attempt = $this->start(); $data = $this->provider($attempt); $data['data']['attributes']['payments'][0]['attributes']['status'] = 'failed';
        $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response($data)]);
        $this->webhook()->assertOk(); $this->assertSame('pending', $attempt->fresh()->status); $this->assertNotNull($attempt->fresh()->active_member_id);
        $this->assertDatabaseCount('water_receipts', 0);
        $data['data']['attributes']['status'] = 'expired'; $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response($data)]);
        $this->post(route('water.online.check', $attempt))->assertRedirect();
        $this->travel(16)->seconds(); $this->post(route('water.online.check', $attempt))->assertRedirect();
        $this->assertSame('expired', $attempt->fresh()->status); $this->assertNull($attempt->fresh()->active_member_id);
        $this->post(route('water.submit', $this->member), $this->manual())->assertSessionHasNoErrors();
    }

    public function test_manual_pending_or_paid_or_closed_contribution_cannot_start_online(): void
    {
        $this->actingAs($this->faculty);
        foreach (['pending', 'paid', 'waived'] as $status) {
            $this->member->update(['status' => $status]); $this->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertSessionHasErrors('payment');
        }
        $this->member->update(['status' => 'unpaid']); $this->member->collection->update(['status' => 'closed']);
        $this->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertForbidden(); Http::assertNothingSent();
    }

    public function test_unknown_creation_can_be_recovered_by_authenticated_provider_reference(): void
    {
        $attempt = $this->start(); $attempt->update(['provider_session_id' => null, 'checkout_url' => null, 'status' => 'unknown']);
        $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response($this->provider($attempt))]);
        $this->webhook()->assertOk(); $this->assertSame('confirmed', $attempt->fresh()->status); $this->assertDatabaseCount('water_receipts', 1);
    }

    public function test_processing_attempt_blocks_cash_waiver_and_other_users_status_access(): void
    {
        $attempt = $this->start();
        $this->actingAs($this->officer)->post(route('water.cash.store', $this->member), $this->manual())->assertSessionHasErrors('payment');
        $this->post(route('water.waive', $this->member), ['reason' => 'Test', 'confirmed' => 1])->assertSessionHasErrors('payment');
        $this->actingAs(User::factory()->create())->get(route('water.online.show', $attempt))->assertForbidden();
        $this->get(route('water.online.status', $attempt))->assertForbidden();
        $this->post(route('water.online.check', $attempt))->assertForbidden();
        $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_interleaved_second_initiation_reuses_reservation(): void
    {
        $this->actingAs($this->faculty); $calls = 0;
        $this->fake(function () use (&$calls) {
            $calls++;
            $second = app(WaterGatewayService::class)->initiate($this->member);
            $this->assertSame('creating', $second->status);
            return Http::response($this->creation());
        });
        $first = app(WaterGatewayService::class)->initiate($this->member);
        $this->assertSame(1, $calls); $this->assertSame('pending', $first->status); $this->assertDatabaseCount('water_gateway_attempts', 1);
    }

    public function test_late_confirmation_after_manual_settlement_is_an_exception_not_second_receipt(): void
    {
        $attempt = $this->start(); $attempt->update(['status' => 'expired', 'active_member_id' => null]);
        $this->post(route('water.submit', $this->member), $this->manual())->assertSessionHasNoErrors();
        $this->actingAs($this->officer)->post(route('water.review', WaterSubmission::first()), ['decision' => 'approve', 'confirmed' => 1])->assertSessionHasNoErrors();
        $this->fake(['api.paymongo.com/v1/checkout_sessions/*' => Http::response($this->provider($attempt))]);
        $this->webhook()->assertOk(); $this->assertSame('attention', $attempt->fresh()->status); $this->assertDatabaseCount('water_receipts', 1);
    }

    public function test_interleaved_confirmations_generate_one_receipt_and_notice(): void
    {
        $attempt = $this->start(); $inside = false;
        $this->fake(function () use ($attempt, &$inside) {
            if (! $inside) {
                $inside = true;
                app(WaterGatewayService::class)->reconcile('cs_testOne');
            }
            return Http::response($this->provider($attempt));
        });
        app(WaterGatewayService::class)->reconcile('cs_testOne');
        $this->assertDatabaseCount('water_receipts', 1); $this->assertDatabaseCount('water_submissions', 1); $this->assertDatabaseCount('water_notices', 2);
    }

    public function test_unique_database_reservation_rejects_second_active_attempt(): void
    {
        $attempt = $this->start();
        $this->expectException(\Illuminate\Database\QueryException::class);
        WaterGatewayAttempt::create(['reference' => (string) \Illuminate\Support\Str::uuid(), 'water_member_id' => $this->member->id,
            'active_member_id' => $this->member->id, 'amount_minor' => 50000]);
    }

    public function test_creation_errors_are_sanitized_and_never_interpreted_as_success(): void
    {
        foreach ([Http::response([], 503), Http::response([], 401), Http::response('not json', 200), Http::response(['data' => ['type' => 'checkout_session', 'id' => 'cs_test', 'attributes' => ['livemode' => true, 'checkout_url' => 'https://checkout.paymongo.com/test']]])] as $response) {
            $this->fake(['*' => $response]);
            try { app(PayMongoGateway::class)->createCheckout('test-reference', 50000, 'PHP', 'https://example.test/return'); $this->fail('Expected a rejected response.'); }
            catch (\App\Services\Payments\GatewayUnavailable $e) { $this->assertSame('Checkout creation was not confirmed.', $e->getMessage()); $this->assertNull($e->getPrevious()); }
        }
    }

    public function test_expired_session_with_inflight_payment_does_not_release_reservation(): void
    {
        $attempt = $this->start(); $data = $this->provider($attempt);
        $data['data']['attributes']['status'] = 'expired';
        $data['data']['attributes']['payments'][0]['attributes']['status'] = 'pending';
        $this->fake(['*' => Http::response($data)]);
        $this->webhook()->assertOk(); $this->assertNotNull($attempt->fresh()->active_member_id); $this->assertSame('pending', $attempt->fresh()->status);
    }

    public function test_treasurer_cannot_manually_approve_an_automatic_payment_and_faculty_cannot_review(): void
    {
        $attempt = $this->start(); $this->fake(['*' => Http::response($this->provider($attempt))]); $this->webhook()->assertOk();
        $submission = WaterSubmission::firstOrFail();
        $this->actingAs($this->faculty)->post(route('water.review', $submission), ['decision' => 'approve', 'confirmed' => 1])->assertForbidden();
        $this->actingAs($this->officer)->post(route('water.review', $submission), ['decision' => 'approve', 'confirmed' => 1])->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('water_receipts', 1);
        $this->get(route('water.queue'))->assertOk()->assertDontSee($this->faculty->full_name);
    }

    public function test_manual_payment_is_still_available_before_optional_gateway_migration(): void
    {
        \Illuminate\Support\Facades\Schema::drop('water_gateway_attempts');
        $this->actingAs($this->faculty)->get(route('water.checkout', $this->member))->assertOk()
            ->assertSee('Manual QR Payment')->assertDontSee('PayMongo')->assertDontSee('data-gateway-start');
        $this->post(route('water.submit', $this->member), $this->manual())->assertSessionHasNoErrors();
        $this->assertSame('pending', $this->member->fresh()->status);
    }

    public function test_local_status_poll_returns_no_checkout_secret_and_provider_checks_have_cooldown(): void
    {
        $attempt = $this->start(); $this->fake(['*' => Http::response($this->provider($attempt, false))]);
        $this->post(route('water.online.check', $attempt))->assertRedirect();
        $this->post(route('water.online.check', $attempt))->assertRedirect();
        Http::assertSentCount(1);
        $this->get(route('water.online.status', $attempt))->assertExactJson(['status' => 'pending', 'receipt_url' => null]);
        $this->assertSame('Online payment processing', $this->member->fresh()->status_label);
    }

    public function test_legacy_event_envelope_has_the_same_signature_and_reconciliation_checks(): void
    {
        $attempt = $this->start(); $this->fake(['*' => Http::response($this->provider($attempt))]);
        $raw = json_encode(['data' => ['id' => 'evt_synthetic', 'type' => 'event', 'attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'data' => ['type' => 'checkout_session', 'id' => 'cs_testOne']]]]);
        $t = time(); $sig = hash_hmac('sha256', $t.'.'.$raw, config('payment_gateway.webhook_secret'));
        $this->call('POST', route('water.gateway.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t=$t,te=$sig,li="], $raw)->assertOk();
        $this->assertSame('confirmed', $attempt->fresh()->status);
    }
}
