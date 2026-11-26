<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\WaterReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletService
{
    public function __construct(private ?TransferProvider $provider = null)
    {
        $this->provider ??= new TransferSimulator;
    }

    private function require(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['wallet' => $message]);
        }
    }

    public function treasurer(User $u): void
    {
        SimulationBoundary::check();
        abort_unless($u->role === 'treasurer', 403);
    }

    public static function minor(string $v): int
    {
        if (! preg_match('/^(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?$/D', $v, $m)) {
            throw ValidationException::withMessages(['amount' => 'Enter a PHP amount with at most two decimals.']);
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function lock(): object
    {
        SimulationBoundary::check();
        $a = DB::table('wallet_accounts')->lockForUpdate()->first();
        $this->require($a && $a->mode === 'simulated' && $a->currency === 'PHP', 'Simulated PHP account unavailable.');

        return $a;
    }

    public function balances(?int $collection = null): array
    {
        SimulationBoundary::check();
        $q = DB::table('wallet_entries');
        if ($collection !== null) {
            $q->where('collection_id', $collection);
        }

        return array_map('intval', (array) $q->selectRaw('COALESCE(SUM(available),0) available, COALESCE(SUM(pending),0) pending, COALESCE(SUM(reserved),0) reserved, COALESCE(SUM(withdrawn),0) withdrawn, COALESCE(SUM(fees),0) fees')->first());
    }

    public function reconcile(): array
    {
        SimulationBoundary::check();
        $expected = ['available' => 0, 'pending' => 0, 'reserved' => 0, 'withdrawn' => 0, 'fees' => 0];
        $valid = true;
        foreach (DB::table('wallet_settlements')->get() as $settlement) {
            $receipt = WaterReceipt::with('member')->find($settlement->receipt_id);
            $valid = $valid && $receipt && $receipt->payment_method === 'xendit_test' && self::minor($receipt->amount) === (int) $settlement->gross_minor && (int) $receipt->member->water_collection_id === (int) $settlement->collection_id;
            $valid = $valid && in_array($settlement->status, ['pending', 'settled'], true) && $settlement->fee_minor >= 0 && $settlement->fee_minor < $settlement->gross_minor;
            $net = $settlement->gross_minor - $settlement->fee_minor;
            $expected[$settlement->status === 'settled' ? 'available' : 'pending'] += $net;
            $expected['fees'] += $settlement->fee_minor;
        }
        foreach (DB::table('wallet_withdrawals')->get() as $withdrawal) {
            $valid = $valid && in_array($withdrawal->status, ['pending', 'processing', 'completed', 'failed', 'reversed'], true);
            if (in_array($withdrawal->status, ['pending', 'processing'], true)) {
                $expected['available'] -= $withdrawal->amount_minor + $withdrawal->fee_minor;
                $expected['reserved'] += $withdrawal->amount_minor + $withdrawal->fee_minor;
            }
            if ($withdrawal->status === 'completed') {
                $expected['available'] -= $withdrawal->amount_minor + $withdrawal->fee_minor;
                $expected['withdrawn'] += $withdrawal->amount_minor;
                $expected['fees'] += $withdrawal->fee_minor;
            }
        }

        return ['matches' => $valid && $expected === $this->balances() && min($expected) >= 0, 'expected' => $expected, 'ledger' => $this->balances()];
    }

    private function entry(int $a, int $c, string $key, string $kind, array $d, ?int $w = null): void
    {
        DB::table('wallet_entries')->insert($d + ['account_id' => $a, 'collection_id' => $c, 'event_key' => $key, 'kind' => $kind, 'withdrawal_id' => $w, 'created_at' => now()]);
    }

    public function collected(int $c): int
    {
        SimulationBoundary::check();

        return WaterReceipt::whereHas('member', fn ($q) => $q->where('water_collection_id', $c))->get()->sum(fn ($r) => self::minor($r->amount));
    }

    public function target(User $u, int $c, int $amount): void
    {
        $this->treasurer($u);
        DB::transaction(function () use ($u, $c, $amount) {
            $this->lock();
            $this->require($amount > 0 && DB::table('water_collections')->where('id', $c)->exists(), 'Positive collection target required.');
            DB::table('wallet_targets')->updateOrInsert(['collection_id' => $c], ['amount_minor' => $amount, 'updated_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function recognize(User $u, int $receipt, int $fee = 0): void
    {
        $this->treasurer($u);
        DB::transaction(function () use ($receipt, $fee) {
            $a = $this->lock();
            $r = WaterReceipt::with('member')->findOrFail($receipt);
            $gross = self::minor($r->amount);
            $this->require($r->payment_method === 'xendit_test' && $r->member->status === 'paid', 'Only verified synthetic Xendit receipts can fund this simulation.');
            $this->require($fee >= 0 && $fee < $gross, 'Invalid settlement fee.');
            $old = DB::table('wallet_settlements')->where('receipt_id', $receipt)->first();
            if ($old) {
                $this->require((int) $old->gross_minor === $gross && (int) $old->fee_minor === $fee, 'Conflicting settlement.');

                return;
            }
            $id = DB::table('wallet_settlements')->insertGetId(['account_id' => $a->id, 'collection_id' => $r->member->water_collection_id, 'receipt_id' => $receipt, 'gross_minor' => $gross, 'fee_minor' => $fee, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->entry($a->id, $r->member->water_collection_id, 'settlement:'.$id.':pending', 'Pending simulated settlement', ['pending' => $gross - $fee, 'fees' => $fee]);
        });
    }

    public function settle(User $u, int $id): void
    {
        $this->treasurer($u);
        DB::transaction(function () use ($id) {
            $this->lock();
            $s = DB::table('wallet_settlements')->where('id', $id)->first();
            abort_unless($s, 404);
            if ($s->status === 'settled') {
                return;
            }$this->require($s->status === 'pending', 'Invalid settlement state.');
            $net = $s->gross_minor - $s->fee_minor;
            $this->entry($s->account_id, $s->collection_id, 'settlement:'.$id.':settled', 'SIMULATED settlement', ['pending' => -$net, 'available' => $net]);
            DB::table('wallet_settlements')->where('id', $id)->update(['status' => 'settled', 'updated_at' => now()]);
        });
    }

    public function request(User $u, int $c, int $destination, string $version, int $amount, string $key): object
    {
        $this->treasurer($u);

        return DB::transaction(function () use ($u, $c, $destination, $version, $amount, $key) {
            $a = $this->lock();
            $this->require(Str::isUuid($key), 'Invalid request reference.');
            $old = DB::table('wallet_withdrawals')->where('idempotency_key', $key)->first();
            if ($old) {
                $this->require((int) $old->requested_by === $u->id && (int) $old->collection_id === $c && (int) $old->destination_id === $destination && $old->destination_version === $version && (int) $old->amount_minor === $amount, 'Conflicting duplicate request.');

                return $old;
            }
            $this->require($this->reconcile()['matches'], 'Ledger reconciliation mismatch; requests are blocked.');
            $d = DB::table('wallet_destinations')->where('id', $destination)->where('account_id', $a->id)->where('active', true)->first();
            $this->require($d && hash_equals($d->version, $version), 'Destination changed; review again.');
            $target = DB::table('wallet_targets')->where('collection_id', $c)->first();
            $this->require($target && $target->amount_minor > 0 && $this->collected($c) >= $target->amount_minor, 'Collection target has not been reached.');
            $fee = $this->provider->fee();
            $total = $amount + $fee;
            $this->require($fee >= 0 && $amount > 0 && $total <= $this->balances($c)['available'] && $total <= $this->balances()['available'], 'Insufficient settled available funds.');
            $id = DB::table('wallet_withdrawals')->insertGetId(['reference' => (string) Str::uuid(), 'idempotency_key' => $key, 'account_id' => $a->id, 'collection_id' => $c, 'destination_id' => $destination, 'destination_version' => $version, 'destination_snapshot' => $d->label.' · '.$d->masked_number, 'requested_by' => $u->id, 'amount_minor' => $amount, 'fee_minor' => $fee, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->entry($a->id, $c, 'withdrawal:'.$id.':pending', 'SIMULATED withdrawal reserved', ['available' => -$total, 'reserved' => $total], $id);

            return DB::table('wallet_withdrawals')->where('id', $id)->first();
        }, 3);
    }

    public function transition(User $u, int $id, string $outcome): void
    {
        $this->treasurer($u);
        $outcome = (new TransferSimulator)->outcome($outcome);
        DB::transaction(function () use ($id, $outcome) {
            $this->lock();
            $w = DB::table('wallet_withdrawals')->where('id', $id)->first();
            abort_unless($w, 404);
            if ($w->status === $outcome) {
                return;
            }
            $result = $this->provider->result($w, $outcome);
            $expectedStatus = match ($outcome) {
                'processing' => 'ROUTING', 'completed' => 'SUCCEEDED', 'failed' => 'FAILED', 'reversed' => 'REVERSED'
            };
            $this->require(($result['provider_status'] ?? null) === $expectedStatus, 'Contradictory simulated provider status.');
            $this->require(($result['simulated'] ?? null) === true && ($result['reference'] ?? null) === $w->reference && ($result['currency'] ?? null) === 'PHP' && ($result['amount_minor'] ?? null) === (int) $w->amount_minor && ($result['destination_version'] ?? null) === $w->destination_version && ($result['status'] ?? null) === $outcome, 'Ambiguous or mismatched simulated provider result.');
            $allowed = ['pending' => ['processing', 'failed'], 'processing' => ['completed', 'failed'], 'completed' => ['reversed'], 'failed' => [], 'reversed' => []];
            $this->require(in_array($outcome, $allowed[$w->status] ?? [], true), 'Conflicting or late outcome; preserve current state.');
            $total = $w->amount_minor + $w->fee_minor;
            $d = match ($outcome) {
                'processing' => [],'completed' => ['reserved' => -$total, 'withdrawn' => $w->amount_minor, 'fees' => $w->fee_minor],'failed' => ['reserved' => -$total, 'available' => $total],'reversed' => ['withdrawn' => -$w->amount_minor, 'fees' => -$w->fee_minor, 'available' => $total]
            };
            $this->entry($w->account_id, $w->collection_id, 'withdrawal:'.$id.':'.$outcome, 'SIMULATED '.$outcome, $d, $id);
            DB::table('wallet_withdrawals')->where('id', $id)->update(['status' => $outcome, 'updated_at' => now()]);
        }, 3);
    }
}
