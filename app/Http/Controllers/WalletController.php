<?php

namespace App\Http\Controllers;

use App\Models\WaterCollection;
use App\Models\WaterReceipt;
use App\Services\Wallet\CollectionTargets;
use App\Services\Wallet\SimulationBoundary;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class WalletController extends Controller
{
    public function index(Request $r, WalletService $s)
    {
        abort_unless(in_array($r->user()->role, ['treasurer', 'admin', 'president'], true), 403);
        if (! config('wallet.enabled')) {
            abort_unless($r->user()->hasPermission('payments.view'), 403);
            $collections = WaterCollection::with('members')->orderByDesc('id')->get();
            $r->validate(['method' => 'nullable|string|max:20', 'collection' => 'nullable|integer|min:1']);
            $receipts = WaterReceipt::with('member.collection')->when($r->filled('method'), fn ($q) => $q->where('payment_method', $r->input('method')))->when($r->filled('collection'), fn ($q) => $q->whereHas('member', fn ($m) => $m->where('water_collection_id', $r->integer('collection'))))->orderByDesc('id')->paginate(15)->withQueryString();
            $methods = WaterReceipt::all()->groupBy('payment_method')->map(fn ($rows) => $rows->sum(fn ($row) => WalletService::minor($row->amount)));
            $outstanding = $collections->sum(fn ($c) => $c->members->whereNotIn('status', ['paid', 'waived'])->sum(fn ($m) => WalletService::minor($m->assigned_amount)));
            $targetsReady = app(CollectionTargets::class)->ready();
            $targets = $targetsReady ? DB::table('wallet_targets')->get()->keyBy('collection_id') : collect();
            $receiptTotals = WaterReceipt::with('member')->get()->groupBy(fn ($receipt) => $receipt->member->water_collection_id)->map(fn ($rows) => $rows->sum(fn ($row) => WalletService::minor($row->amount)));

            $receiptIssues=WaterReceipt::where(function($q){$q->whereDoesntHave('member',fn($m)=>$m->where('status','paid'))->orWhereDoesntHave('submission',fn($s)=>$s->where('status','approved')->whereColumn('submitted_amount','water_receipts.amount')->whereColumn('water_member_id','water_receipts.water_member_id'));})->count();
            return view('wallet.overview', compact('collections', 'receipts', 'methods', 'outstanding', 'targetsReady', 'targets', 'receiptTotals','receiptIssues'));
        }
        SimulationBoundary::check();
        $collections = WaterCollection::orderByDesc('id')->get()->map(function ($c) use ($s) {
            $c->collected_minor = $s->collected($c->id);
            $c->target_minor = (int) DB::table('wallet_targets')->where('collection_id', $c->id)->value('amount_minor');
            $c->wallet = $s->balances($c->id);
            $c->outstanding_minor = $c->members()->whereNotIn('status', ['paid', 'waived'])->get()->sum(fn ($m) => WalletService::minor($m->assigned_amount));

            return $c;
        });
        $methods = WaterReceipt::all()->groupBy('payment_method')->map(fn ($rows) => $rows->sum(fn ($row) => WalletService::minor($row->amount)));

        return view('wallet.index', ['collections' => $collections, 'balance' => $s->balances(), 'methods' => $methods, 'reconciliation' => $s->reconcile(),
            'destinations' => DB::table('wallet_destinations')->where('active', true)->get(),
            'withdrawals' => DB::table('wallet_withdrawals')->orderByDesc('id')->paginate(10),
            'settlements' => DB::table('wallet_settlements')->where('status', 'pending')->get(),
            'activity' => DB::table('wallet_entries')->orderByDesc('id')->limit(8)->get()]);
    }

    public function target(Request $r, WalletService $s)
    {
        if (! config('wallet.enabled')) {
            abort_unless($r->user()->role === 'treasurer' && $r->user()->hasPermission('payments.manage'), 403);
            $v = $r->validate(['collection' => 'required|integer|min:1', 'amount' => 'required|string', 'revision' => 'required|integer|min:0', 'operation_key' => 'required|uuid']);
            app(CollectionTargets::class)->set($r->user(), $v['collection'], WalletService::minor($v['amount']), $v['revision'], $v['operation_key']);

            return back()->with('status', 'Collection target updated. Provider balances are unchanged.');
        }
        $s->treasurer($r->user());
        $v = $r->validate(['collection' => 'required|integer', 'amount' => 'required|string']);
        $s->target($r->user(), $v['collection'], WalletService::minor($v['amount']));

        return back()->with('status', 'Simulation target saved.');
    }

    public function review(Request $r, WalletService $s)
    {
        $s->treasurer($r->user());
        $v = $r->validate(['collection' => 'required|integer', 'destination' => 'required|integer', 'amount' => 'required|string']);
        $d = DB::table('wallet_destinations')->where('id', $v['destination'])->where('active', true)->first();
        abort_unless($d, 404);
        $c = WaterCollection::findOrFail($v['collection']);
        $amount = WalletService::minor($v['amount']);
        $target = (int) DB::table('wallet_targets')->where('collection_id', $c->id)->value('amount_minor');
        if ($amount <= 0 || $target <= 0 || $s->collected($c->id) < $target || $amount > $s->balances($c->id)['available']) {
            throw ValidationException::withMessages(['amount' => 'Target must be reached and settled funds sufficient.']);
        }
        $intent = ['collection' => $c->id, 'destination' => $d->id, 'version' => $d->version, 'amount' => $amount, 'key' => (string) Str::uuid(), 'user' => $r->user()->id, 'expires' => time() + 300];
        $r->session()->put('wallet.intent', $intent);

        return view('wallet.review', compact('intent', 'd', 'c'));
    }

    public function confirm(Request $r, WalletService $s)
    {
        $s->treasurer($r->user());
        $r->validate(['password' => 'required|string', 'key' => 'required|uuid']);
        $i = $r->session()->get('wallet.intent');
        if (! $i || $i['expires'] < time() || $i['user'] !== $r->user()->id || ! hash_equals($i['key'], $r->input('key'))) {
            throw ValidationException::withMessages(['wallet' => 'Review expired; start a new review.']);
        }
        if (! Hash::check($r->input('password'), $r->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Password verification failed.']);
        }
        // Destination, collection and amount come only from the reviewed server-side intent.
        $s->request($r->user(), $i['collection'], $i['destination'], $i['version'], $i['amount'], $i['key']);

        return redirect()->route('wallet.index')->with('status', 'SIMULATED withdrawal reserved. No money was sent.');
    }

    public function simulate(Request $r, WalletService $s, int $withdrawal)
    {
        $r->validate(['outcome' => 'required|in:processing,completed,failed,reversed']);
        $s->transition($r->user(), $withdrawal, $r->input('outcome'));

        return back()->with('status', 'Simulated provider outcome recorded.');
    }

    public function settle(Request $r, WalletService $s, int $settlement)
    {
        $s->settle($r->user(), $settlement);

        return back()->with('status','SIMULATED settlement released.');
    }
}
