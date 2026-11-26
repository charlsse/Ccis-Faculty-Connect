<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaterReceipt;
use App\Services\Wallet\SyntheticFixtures;
use App\Services\Wallet\TransferProvider;
use App\Services\Wallet\TransferSimulator;
use App\Services\Wallet\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TreasurerWalletTest extends TestCase
{
    use RefreshDatabase;

    private array $f;

    private WalletService $s;

    private User $treasurer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['wallet.enabled' => true]);
        Http::preventStrayRequests();
        (require database_path('experimental/wallet/2026_10_18_230000_create_simulated_wallet.php'))->up();
        $this->f = SyntheticFixtures::seed('synthetic-password');
        $this->s = new WalletService;
        $this->treasurer = User::findOrFail($this->f['users']['treasurer']);
    }

    private function requestTransfer(int $amount = 5000, ?string $key = null): object
    {
        $d = DB::table('wallet_destinations')->first();

        return $this->s->request($this->treasurer, $this->f['collections'][0], $d->id, $d->version, $amount, $key ?? (string) Str::uuid());
    }

    public function test_wallet_is_disabled_by_default_and_isolated(): void
    {
        config(['wallet.enabled' => false]);
        $this->actingAs($this->treasurer)->get(route('wallet.index'))->assertOk()->assertSee('Collection overview only')->assertDontSee('Confirm simulated withdrawal');
        $this->post(route('wallet.review'), ['collection'=>1,'destination'=>1,'amount'=>'1.00'])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_receipts_settlements_and_manual_funds_are_separate(): void
    {
        $this->assertSame(['available' => 9900, 'pending' => 7900, 'reserved' => 0, 'withdrawn' => 0, 'fees' => 200], $this->s->balances());
        $this->assertSame(10000, $this->s->collected($this->f['collections'][0]));
        $this->actingAs($this->treasurer)->get(route('wallet.index'))->assertOk()->assertSee('SIMULATED')->assertSee('₱230.00')->assertSee('₱99.00')->assertSee('outside provider wallet')->assertSee('₱80.00');
        $this->expectException(ValidationException::class);
        $this->s->recognize($this->treasurer, WaterReceipt::where('payment_method', 'cash')->value('id'));
    }

    public function test_roles_are_read_only_except_treasurer(): void
    {
        foreach (['admin', 'president', 'faculty'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get(route('wallet.index'))->assertStatus($role === 'faculty' ? 403 : 200);
            $this->post(route('wallet.target'), ['collection' => 1, 'amount' => '1.00'])->assertForbidden();
            $this->post(route('wallet.review'), ['collection' => 1, 'destination' => 1, 'amount' => '1.00'])->assertForbidden();
            $this->post(route('wallet.confirm'), [])->assertForbidden();
            $this->post(route('wallet.simulate', 1), ['outcome' => 'completed'])->assertForbidden();
            $this->post(route('wallet.settle', 1))->assertForbidden();
        }
    }

    public function test_duplicate_confirmation_and_completed_transfer_preserve_history(): void
    {
        $key = (string) Str::uuid();
        $w = $this->requestTransfer(5000, $key);
        $this->assertSame($w->id, $this->requestTransfer(5000, $key)->id);
        foreach (['processing', 'processing', 'completed', 'completed'] as $outcome) {
            $this->s->transition($this->treasurer, $w->id, $outcome);
        }
        $this->assertSame(4900, $this->s->balances()['available']);
        $this->assertSame(5000, $this->s->balances()['withdrawn']);
        $this->assertSame(0, $this->s->balances()['reserved']);
        $this->assertSame(10000, $this->s->collected(1));
        $this->assertDatabaseCount('water_receipts', 3);
        $this->assertDatabaseCount('water_submissions', 3);
        $this->assertDatabaseCount('wallet_withdrawals', 1);
        Http::assertNothingSent();
    }

    public function test_failure_and_reversal_restore_available_exactly_once(): void
    {
        $w = $this->requestTransfer();
        foreach (['failed', 'failed'] as $o) {
            $this->s->transition($this->treasurer, $w->id, $o);
        }$this->assertSame(9900, $this->s->balances()['available']);
        $w = $this->requestTransfer();
        foreach (['processing', 'completed', 'reversed', 'reversed'] as $o) {
            $this->s->transition($this->treasurer, $w->id, $o);
        }
        $this->assertSame(['available' => 9900, 'pending' => 7900, 'reserved' => 0, 'withdrawn' => 0, 'fees' => 200], $this->s->balances());
        $this->expectException(ValidationException::class);
        $this->s->transition($this->treasurer, $w->id, 'completed');
    }

    public function test_reservations_prevent_overspend(): void
    {
        $this->requestTransfer(9000);
        $this->expectException(ValidationException::class);
        $this->requestTransfer(1000);
    }

    public function test_target_and_pending_settlement_block_withdrawal(): void
    {
        $d = DB::table('wallet_destinations')->first();
        $this->s->target($this->treasurer, 2, 8000);
        $this->expectException(ValidationException::class);
        $this->s->request($this->treasurer, 2, $d->id, $d->version, 100, (string) Str::uuid());
    }

    public function test_unreached_quota_blocks_even_settled_funds(): void
    {
        $this->s->target($this->treasurer, 1, 11000);
        $this->expectException(ValidationException::class);
        $this->requestTransfer();
    }

    public function test_duplicate_key_different_amount_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $this->requestTransfer(5000, $key);
        $this->expectException(ValidationException::class);
        $this->requestTransfer(4000, $key);
    }

    public function test_review_requires_password_and_ignores_recipient_amount_tampering(): void
    {
        $this->actingAs($this->treasurer)->post(route('wallet.review'), ['collection' => 1, 'destination' => 1, 'amount' => '50.00'])->assertOk()->assertSee('Locked destination');
        $key = session('wallet.intent.key');
        $this->post(route('wallet.confirm'), ['key' => $key, 'password' => 'incorrect'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('wallet_withdrawals', 0);
        $this->post(route('wallet.confirm'), ['key' => $key, 'password' => 'synthetic-password', 'destination' => 999, 'amount' => '0.01'])->assertRedirect(route('wallet.index'));
        $this->assertDatabaseHas('wallet_withdrawals', ['amount_minor' => 5000, 'destination_id' => 1, 'status' => 'pending']);
    }

    public function test_changed_destination_and_expired_review_fail_closed(): void
    {
        $this->actingAs($this->treasurer)->post(route('wallet.review'), ['collection' => 1, 'destination' => 1, 'amount' => '50.00']);
        $key = session('wallet.intent.key');
        DB::table('wallet_destinations')->where('id', 1)->update(['version' => (string) Str::uuid()]);
        $this->post(route('wallet.confirm'), ['key' => $key, 'password' => 'synthetic-password'])->assertSessionHasErrors('wallet');
        $i = session('wallet.intent');
        $i['expires'] = time() - 1;
        $this->withSession(['wallet.intent' => $i])->post(route('wallet.confirm'), ['key' => $key, 'password' => 'synthetic-password'])->assertSessionHasErrors('wallet');
        $this->assertDatabaseCount('wallet_withdrawals', 0);
    }

    public function test_settlement_duplicate_and_reconciliation_conservation(): void
    {
        $this->s->settle($this->treasurer, 2);
        $this->s->settle($this->treasurer, 2);
        $b = $this->s->balances();
        $this->assertSame(18000, array_sum($b));
        $this->assertSame(17800, $b['available']);
        $this->assertSame(0, $b['pending']);
    }

    public function test_rollback_refuses_accounting_history(): void
    {
        $this->expectException(\RuntimeException::class);
        (require database_path('experimental/wallet/2026_10_18_230000_create_simulated_wallet.php'))->down();
    }

    public function test_ambiguous_provider_result_preserves_reservation(): void
    {
        $w = $this->requestTransfer();
        $provider = new class implements TransferProvider
        {
            public function fee(): int
            {
                return 0;
            }

            public function result(object $w, string $scenario): array
            {
                return ['status' => $scenario];
            }
        };
        try {
            (new WalletService($provider))->transition($this->treasurer, $w->id, 'processing');
            $this->fail('Ambiguous result accepted');
        } catch (ValidationException $e) {
            $this->assertSame('pending', DB::table('wallet_withdrawals')->where('id', $w->id)->value('status'));
            $this->assertSame(5000, $this->s->balances()['reserved']);
        }
    }

    public function test_documented_status_vocabulary_and_reconciliation(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/xendit-payout-statuses.json')), true);
        $w = $this->requestTransfer();
        foreach ($fixture['cases'] as $case) {
            $r = (new TransferSimulator)->result($w, $case['local_outcome']);
            $this->assertSame($case['provider_status'], $r['provider_status']);
            $this->assertTrue($r['simulated']);
        }
        $this->assertTrue($this->s->reconcile()['matches']);
        DB::table('wallet_entries')->where('event_key', 'withdrawal:'.$w->id.':pending')->update(['available' => -1]);
        $this->assertFalse($this->s->reconcile()['matches']);
        $this->expectException(ValidationException::class);
        $this->requestTransfer(100);
    }

    public function test_faculty_history_remains_accessible_and_no_extra_payment_is_created(): void
    {
        $u = User::findOrFail($this->f['users']['faculty']);
        $this->actingAs($u)->get(route('water.demo.index'))->assertOk()->assertSee('Faculty activity fund');
        $this->get(route('water.receipt',WaterReceipt::first()))->assertOk();
        $this->assertDatabaseCount('water_receipts',3);
        Http::assertNothingSent();
    }
}
