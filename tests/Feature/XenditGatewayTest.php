<?php

namespace Tests\Feature;

use App\Models\{User, WaterCollection, WaterGatewayAttempt, WaterMember, WaterPaymentAccount};
use App\Services\Payments\{XenditGateway, WaterGatewayService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class XenditGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $faculty;
    private WaterMember $member;
    private const SESSION = 'ps-661f87c614802d6c402cd82d';
    private const REQUEST = 'pr-1102feb0-bb79-47ae-9d1e-e69394d3949c';
    private const PAYMENT = 'py-1102feb0-bb79-47ae-9d1e-e69394d3949c';
    private const BUSINESS = '661f87c614802d6c402cd82d';

    protected function setUp(): void
    {
        parent::setUp();
        // TestCase refuses all databases except SQLite :memory: before schema setup.
        (require database_path('experimental/xendit/2026_10_18_180000_add_xendit_gateway_attempts.php'))->up();
        Storage::fake('local');
        config(['app.url' => 'https://faculty-test.ngrok-free.app', 'water.email_enabled' => false,
            'payment_gateway.enabled' => true, 'payment_gateway.provider' => 'xendit', 'payment_gateway.environment' => 'sandbox',
            'payment_gateway.sandbox_data_confirmed' => true, 'payment_gateway.xendit.treasurer_account_confirmed' => true,
            'payment_gateway.xendit.secret_key' => 'xnd_development_synthetic_not_a_real_key',
            'payment_gateway.xendit.callback_token' => 'synthetic_callback_token_for_tests', 'payment_gateway.xendit.business_id' => self::BUSINESS]);
        $this->faculty = User::factory()->create();
        $treasurer = User::factory()->create(['role' => 'treasurer']);
        $account = WaterPaymentAccount::create(['label' => 'Synthetic', 'account_name' => 'Synthetic club', 'masked_account_number' => '***1234', 'qr_image_path' => 'synthetic.png', 'created_by' => $treasurer->id, 'is_active' => true]);
        $collection = WaterCollection::create(['title' => 'Synthetic Xendit test', 'period' => now()->format('Y-m'), 'amount' => '500.00', 'due_date' => now()->addWeek(), 'status' => 'published', 'published_at' => now(), 'payment_account_id' => $account->id, 'created_by' => $treasurer->id]);
        $this->member = $collection->members()->create(['user_id' => $this->faculty->id, 'assigned_amount' => '500.00', 'assigned_at' => now(), 'status' => 'unpaid']);
        $this->fake();
    }

    private function providerSession(string $reference, string $status = 'ACTIVE'): array
    {
        return ['payment_session_id' => self::SESSION, 'reference_id' => $reference, 'session_type' => 'PAY', 'mode' => 'PAYMENT_LINK',
            'business_id' => self::BUSINESS, 'currency' => 'PHP', 'country' => 'PH', 'amount' => 500, 'status' => $status,
            'payment_link_url' => 'https://checkout-staging.xendit.co/sessions/'.self::SESSION,
            'payment_request_id' => self::REQUEST, 'payment_id' => self::PAYMENT];
    }

    public function test_unified_checkout_promotes_only_available_test_xendit_and_retains_manual_qr(): void
    {
        $this->actingAs($this->faculty)->get(route('water.checkout',$this->member))->assertOk()->assertSee('Continue to Payment')->assertSee('simulation')->assertSee('Manual QR Payment')->assertDontSee('data-qr-dialog', false);
        Http::assertNothingSent();
        config(['payment_gateway.enabled'=>false]);
        $this->get(route('water.checkout',$this->member))->assertOk()->assertSee('Online payment is temporarily unavailable')->assertSee('Manual QR Payment')->assertDontSee('data-gateway-start');
        Http::assertNothingSent();
    }

    private function payment(): array
    {
        return ['payment_request_id' => self::REQUEST, 'business_id' => self::BUSINESS, 'country' => 'PH', 'currency' => 'PHP',
            'request_amount' => 500, 'type' => 'PAY', 'capture_method' => 'AUTOMATIC', 'status' => 'SUCCEEDED',
            'latest_payment_id' => self::PAYMENT, 'captures' => [['capture_id'=>'opaque-synthetic-capture', 'capture_amount' => 500, 'capture_timestamp' => now()->toIso8601String()]]];
    }

    private function fake(?array $session = null, ?array $payment = null, ?array $captured = null): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        $request = $payment ?? $this->payment();
        $captured ??= array_replace($request, ['payment_id'=>self::PAYMENT]);
        unset($request['captures']);
        Http::fake([
            'api.xendit.co/sessions' => fn ($r) => Http::response($this->providerSession($r['reference_id'])),
            'api.xendit.co/sessions/*' => Http::response($session ?? []),
            'api.xendit.co/v3/payment_requests/*' => Http::response($request),
            'api.xendit.co/v3/payments/*' => Http::response($captured),
        ]);
    }

    private function start(): WaterGatewayAttempt
    {
        $this->actingAs($this->faculty)->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1, 'amount' => 1])
            ->assertRedirect('https://checkout-staging.xendit.co/sessions/'.self::SESSION);
        return WaterGatewayAttempt::firstOrFail();
    }

    private function webhook(WaterGatewayAttempt $attempt, string $event = 'payment_session.completed', array $changes = [], ?string $token = null)
    {
        $body = ['event' => $event, 'business_id' => self::BUSINESS,
            'data' => array_replace($this->providerSession($attempt->display_reference, $event === 'payment_session.expired' ? 'EXPIRED' : 'COMPLETED'), $changes)];
        return $this->call('POST', route('water.gateway.xendit'), [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CALLBACK_TOKEN' => $token ?? config('payment_gateway.xendit.callback_token')], json_encode($body));
    }

    public function test_server_amount_hosted_session_and_single_creation(): void
    {
        $attempt = $this->start();
        $this->assertSame(50000, $attempt->amount_minor);
        $this->assertSame('pending', $attempt->status);
        $this->post(route('water.online.start', $this->member), ['test_mode_confirmed' => 1])->assertRedirect();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['amount'] === 500 && $r['session_type'] === 'PAY' && $r['mode'] === 'PAYMENT_LINK'
            && $r['currency'] === 'PHP' && $r['allow_save_payment_method'] === 'DISABLED' && $r['capture_method'] === 'AUTOMATIC'
            && $r['success_return_url'] === 'https://faculty-test.ngrok-free.app/payments/xendit/return'
            && ! $r->hasHeader('for-user-id') && $r['customer']['individual_detail']['surname'] === 'Test');
        $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_success_duplicate_and_late_expiration_are_idempotent(): void
    {
        $a = $this->start(); $this->fake($this->providerSession($a->display_reference, 'COMPLETED'));
        $this->webhook($a)->assertOk(); $this->webhook($a)->assertOk();
        $this->webhook($a, 'payment_session.expired')->assertOk();
        $this->assertSame('confirmed', $a->fresh()->status); $this->assertSame('paid', $this->member->fresh()->status);
        $this->assertDatabaseCount('water_receipts', 1); $this->assertDatabaseCount('water_submissions', 1);
        $this->assertDatabaseHas('water_submissions', ['payment_method' => 'xendit_test', 'verified_by' => null]);
        $this->get(route('water.receipt', \App\Models\WaterReceipt::first()))->assertOk()->assertSee('No Funds Collected')->assertSee('Xendit TEST');
        Http::assertSentCount(3);
    }

    public function test_failed_and_pending_notifications_cannot_mark_paid(): void
    {
        $a = $this->start(); $p = $this->payment(); $p['status'] = 'FAILED';
        $this->fake($this->providerSession($a->display_reference, 'COMPLETED'), $p);
        $this->webhook($a)->assertOk();
        $this->webhook($a, 'payment.failure')->assertOk();
        $this->assertSame('pending', $a->fresh()->status); $this->assertNotNull($a->fresh()->active_member_id);
        $this->assertDatabaseCount('water_receipts', 0); $this->assertSame('unpaid', $this->member->fresh()->status);
    }

    public function test_expiration_is_verified_before_releasing_reservation(): void
    {
        $a = $this->start(); $this->fake($this->providerSession($a->display_reference));
        $this->webhook($a, 'payment_session.expired')->assertOk(); $this->assertNotNull($a->fresh()->active_member_id);
        $this->fake(array_replace($this->providerSession($a->display_reference, 'EXPIRED'), ['payment_request_id' => null, 'payment_id' => null]));
        $this->webhook($a, 'payment_session.expired')->assertOk();
        $this->assertSame('expired', $a->fresh()->status); $this->assertNull($a->fresh()->active_member_id);
        $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_unauthorized_malformed_and_mismatched_notifications_do_not_query_or_pay(): void
    {
        $a = $this->start(); $this->fake();
        $this->webhook($a, token: 'wrong')->assertUnauthorized();
        foreach ([['amount' => 1], ['amount' => '500'], ['payment_session_id' => []], ['currency' => 'USD'], ['reference_id' => 'other'], ['business_id' => str_repeat('f',24)], ['session_type' => 'SAVE'], ['status' => 'ACTIVE']] as $changes) {
            $this->webhook($a, changes: $changes)->assertStatus(400);
        }
        $this->call('POST', route('water.gateway.xendit'), [], [], [], ['CONTENT_TYPE'=>'application/json','HTTP_X_CALLBACK_TOKEN'=>config('payment_gateway.xendit.callback_token')], '{broken')->assertStatus(400);
        Http::assertNothingSent(); $this->assertDatabaseCount('water_receipts', 0);
    }

    public function test_authenticated_api_mismatches_never_pay(): void
    {
        $a = $this->start();
        foreach ([['request_amount'=>1], ['currency'=>'USD'], ['business_id'=>str_repeat('f',24)], ['latest_payment_id'=>'py-00000000-0000-0000-0000-000000000000'], ['captures'=>[]], ['capture_method'=>'MANUAL']] as $changes) {
            $this->fake($this->providerSession($a->display_reference,'COMPLETED'), array_replace($this->payment(),$changes));
            $this->webhook($a)->assertStatus(503); $this->assertDatabaseCount('water_receipts',0);
        }
        $s=$this->providerSession($a->display_reference,'COMPLETED');$s['amount']=499;
        $this->fake($s, array_replace($this->payment(),['request_amount'=>499,'captures'=>[['capture_id'=>'synthetic-other','capture_amount'=>499,'capture_timestamp'=>now()->toIso8601String()]]]));
        $this->webhook($a)->assertOk();$this->assertSame('attention',$a->fresh()->status);$this->assertDatabaseCount('water_receipts',0);
    }

    public function test_browser_redirect_and_status_poll_never_settle(): void
    {
        $a=$this->start();
        $this->get(route('water.online.show',[$a,'status'=>'paid']))->assertOk();
        $this->get(route('water.online.status',$a))->assertOk()->assertJsonPath('status','pending');
        $this->get(route('water.gateway.xendit.return',['status'=>'paid']))->assertOk();
        Http::assertSentCount(1);$this->assertDatabaseCount('water_receipts',0);
    }

    public function test_only_assigned_faculty_can_create(): void
    {
        $this->post(route('water.online.start',$this->member),['test_mode_confirmed'=>1])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('water.online.start',$this->member),['test_mode_confirmed'=>1])->assertForbidden();
        foreach(['admin','president','treasurer'] as $role){
            $this->faculty->update(['role'=>$role]);
            $this->actingAs($this->faculty->fresh())->post(route('water.online.start',$this->member),['test_mode_confirmed'=>1])->assertForbidden();
        }
        Http::assertNothingSent();$this->assertDatabaseCount('water_gateway_attempts',0);
    }

    public function test_live_keys_disabled_flags_and_recovered_database_are_rejected(): void
    {
        $gateway=new XenditGateway;$this->assertTrue($gateway->available());
        foreach(['payment_gateway.enabled'=>false,'payment_gateway.environment'=>'live','payment_gateway.xendit.treasurer_account_confirmed'=>false,'payment_gateway.xendit.secret_key'=>'xnd_production_never_use'] as $key=>$value){
            $old=config($key);config([$key=>$value]);$this->assertFalse($gateway->available());config([$key=>$old]);
        }
        config(['database.default'=>'mysql','database.connections.mysql.database'=>'ccis_local_20261018_164245','payment_gateway.xendit.test_database'=>'ccis_local_20261018_164245']);
        $this->assertFalse($gateway->available());
        config(['database.default'=>'sqlite']);Http::assertNothingSent();
    }

    public function test_lost_creation_response_keeps_reservation_without_retry(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();Http::fake(['api.xendit.co/*'=>Http::response([],503)]);
        $this->actingAs($this->faculty)->post(route('water.online.start',$this->member),['test_mode_confirmed'=>1])->assertRedirect();
        $this->post(route('water.online.start',$this->member),['test_mode_confirmed'=>1])->assertRedirect();
        $a=WaterGatewayAttempt::firstOrFail();$this->assertSame('unknown',$a->status);$this->assertNotNull($a->active_member_id);Http::assertSentCount(1);
    }

    public function test_checkout_url_allowlist_rejects_live_and_untrusted_hosts(): void
    {
        foreach(['https://checkout.xendit.co/a','https://xen.to/a','https://dev.xen.to.evil.test/a','https://user@dev.xen.to/a','http://dev.xen.to/a','https://dev.xen.to:8443/a']as$url)$this->assertFalse(XenditGateway::safeCheckoutUrl($url));
        $this->assertTrue(XenditGateway::safeCheckoutUrl('https://dev.xen.to/test'));
    }

    public function test_expired_checkout_with_captured_or_pending_payment_keeps_reservation(): void
    {
        $a=$this->start();
        foreach (['SUCCEEDED','REQUIRES_ACTION'] as $status) {
            $this->fake(array_replace($this->providerSession($a->display_reference,'EXPIRED'),['payment_id'=>null]), array_replace($this->payment(),['status'=>$status]));
            $this->webhook($a,'payment_session.expired')->assertOk();$this->assertNotNull($a->fresh()->active_member_id);
        }
        $this->assertDatabaseCount('water_receipts',0);
    }

    public function test_latest_payment_request_is_used_after_a_retry(): void
    {
        $a=$this->start();$s=$this->providerSession($a->display_reference,'COMPLETED');
        $s['payment_request_id']='pr-00000000-0000-0000-0000-000000000000';$s['latest_payment_request_id']=self::REQUEST;
        $this->fake($s);$this->webhook($a)->assertOk();
        Http::assertSent(fn($request)=>$request->url()==='https://api.xendit.co/v3/payment_requests/'.self::REQUEST
            && $request->hasHeader('api-version','2024-11-11'));
        $this->assertSame('confirmed',$a->fresh()->status);
    }

    public function test_completed_session_without_matching_captured_payment_identity_is_not_settled(): void
    {
        $a=$this->start();$s=$this->providerSession($a->display_reference,'COMPLETED');$s['payment_id']=null;
        $this->fake($s);$this->webhook($a)->assertStatus(503);$this->assertDatabaseCount('water_receipts',0);
        $this->assertNotNull($a->fresh()->active_member_id);
    }

    public function test_canceled_or_authorized_but_uncaptured_session_stays_reserved(): void
    {
        $a=$this->start();$this->fake($this->providerSession($a->display_reference,'CANCELED'));
        $this->webhook($a)->assertOk();$this->assertSame('pending',$a->fresh()->status);
        $this->fake($this->providerSession($a->display_reference,'COMPLETED'),array_replace($this->payment(),['status'=>'AUTHORIZED','captures'=>[]]));
        $this->webhook($a)->assertOk();$this->assertNotNull($a->fresh()->active_member_id);$this->assertDatabaseCount('water_receipts',0);
    }

    public function test_expiry_with_capture_reference_and_no_request_does_not_release(): void
    {
        $a=$this->start();$s=$this->providerSession($a->display_reference,'EXPIRED');$s['payment_request_id']=null;
        $this->fake($s);$this->webhook($a,'payment_session.expired')->assertOk();
        $this->assertNotNull($a->fresh()->active_member_id);$this->assertSame('pending',$a->fresh()->status);
    }

    public function test_tunnel_is_https_exact_host_and_public_webhook_return_only(): void
    {
        config(['tunnel.enabled'=>true,'tunnel.host'=>'faculty-test.ngrok-free.app','app.debug'=>false,
            'session.secure'=>true,'session.http_only'=>true,'session.same_site'=>'lax',
            'session.domain'=>null,'session.cookie'=>'facultyconnect_xendit_test_mock']);
        $headers=['REMOTE_ADDR'=>'127.0.0.1','HTTP_HOST'=>'faculty-test.ngrok-free.app','HTTP_X_FORWARDED_PROTO'=>'https'];
        foreach(['/login','/dashboard','/up','/.env','/dashboard/water'] as $path){
            $this->call('GET','https://faculty-test.ngrok-free.app'.$path,[],[],[],$headers)->assertNotFound();
        }
        $this->call('GET','https://faculty-test.ngrok-free.app/payments/xendit/return',[],[],[],$headers)->assertOk();
        $this->call('GET','https://attacker.test/payments/xendit/return',[],[],[],array_replace($headers,['HTTP_HOST'=>'attacker.test']))->assertStatus(400);
        $this->call('GET','http://localhost/login',[],[],[],array_replace($headers,['HTTP_HOST'=>'localhost']))->assertStatus(400);
        $this->call('GET','https://faculty-test.ngrok-free.app/payments/xendit/return',[],[],[],array_replace($headers,['HTTP_X_FORWARDED_PROTO'=>'http']))->assertStatus(400);
        config(['app.debug'=>true]);$this->call('GET','https://faculty-test.ngrok-free.app/payments/xendit/return',[],[],[],$headers)->assertStatus(503);
    }

    public function test_empty_opt_in_schema_rolls_back_and_history_blocks_rollback(): void
    {
        $migration=require database_path('experimental/xendit/2026_10_18_180000_add_xendit_gateway_attempts.php');
        $migration->down();$this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('water_gateway_attempts'));
        $migration->up();$this->start();
        $this->expectException(\RuntimeException::class);$migration->down();
    }

    public function test_observed_capture_contract_and_invalid_variants(): void
    {
        $p=json_decode(file_get_contents(base_path('tests/Fixtures/xendit-payment-captured.json')),true,512,JSON_THROW_ON_ERROR);
        $s=array_replace($this->providerSession('FC-WATER-00000000-0000-0000-0000-000000000001','COMPLETED'),['amount'=>10]);
        $request=array_replace($this->payment(),['request_amount'=>10]);unset($request['captures']);
        $this->fake($s,$request,$p);
        $result=app(XenditGateway::class)->retrievePayment(self::SESSION);
        $this->assertSame('succeeded',$result['status']);$this->assertSame(1000,$result['amount_minor']);
        Http::assertSentCount(3);
        Http::assertSent(fn($r)=>$r->method()==='GET'&&$r->url()==='https://api.xendit.co/v3/payments/'.self::PAYMENT&&$r->hasHeader('api-version','2024-11-11'));
        $invalid=[['captures'=>null],['captures'=>[]],['captures'=>['not-a-list'=>$p['captures'][0]]],
            ['payment_id'=>'py-00000000-0000-0000-0000-000000000000'],['payment_request_id'=>'pr-00000000-0000-0000-0000-000000000000'],
            ['business_id'=>str_repeat('f',24)],['currency'=>'IDR'],['country'=>'ID'],['request_amount'=>9],['request_amount'=>'10'],
            ['type'=>'SAVE'],['capture_method'=>'MANUAL'],['status'=>'PENDING'],['status'=>'FAILED'],['status'=>'AUTHORIZED'],['status'=>null],
            ['captures'=>[$p['captures'][0],$p['captures'][0]]]];
        foreach(['',null,123,"bad\nidentifier",str_repeat('a',256)]as$id)$invalid[]=['captures'=>[array_replace($p['captures'][0],['capture_id'=>$id])]];
        foreach(['2026-02-30T12:00:00Z','tomorrow','2026-10-18','2026-10-18T12:00:00','2026-10-18T25:00:00Z','2026-10-18T12:00:00+99:00']as$t)$invalid[]=['captures'=>[array_replace($p['captures'][0],['capture_timestamp'=>$t])]];
        foreach([0,-10,9,11,'10',10.001,null]as$amount)$invalid[]=['captures'=>[array_replace($p['captures'][0],['capture_amount'=>$amount])]];
        $invalid[]=['captures'=>[array_replace($p['captures'][0],['status'=>'FAILED'])]];
        foreach($invalid as$change){
            $this->fake($s,$request,array_replace($p,$change));
            try{app(XenditGateway::class)->retrievePayment(self::SESSION);$this->fail('Invalid captured payment accepted');}
            catch(\App\Services\Payments\GatewayUnavailable $e){$this->assertNotEmpty($e->getMessage());}
            $this->assertDatabaseCount('water_receipts',0);$this->assertDatabaseCount('water_submissions',0);
        }
        // Multiple distinct captures are valid only when their exact sum matches.
        $p['captures']=[array_replace($p['captures'][0],['capture_amount'=>4]),array_replace($p['captures'][0],['capture_id'=>'different-opaque-id','capture_amount'=>6])];
        $this->fake($s,$request,$p);$this->assertSame('succeeded',app(XenditGateway::class)->retrievePayment(self::SESSION)['status']);
    }

    public function test_conflicting_request_capture_evidence_fails_closed(): void
    {
        $request=$this->payment();$request['captures']=[];
        $payment=array_replace($this->payment(),['payment_id'=>self::PAYMENT]);
        Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();
        Http::fake([
            'api.xendit.co/sessions/*'=>Http::response($this->providerSession('synthetic-reference','COMPLETED')),
            'api.xendit.co/v3/payment_requests/*'=>Http::response($request),
            'api.xendit.co/v3/payments/*'=>Http::response($payment),
        ]);
        try{app(XenditGateway::class)->retrievePayment(self::SESSION);$this->fail('Conflicting evidence accepted');}
        catch(\App\Services\Payments\GatewayUnavailable $e){$this->assertSame('Conflicting capture evidence.',$e->getMessage());}
        $this->assertDatabaseCount('water_receipts',0);$this->assertDatabaseCount('water_submissions',0);
    }
}
