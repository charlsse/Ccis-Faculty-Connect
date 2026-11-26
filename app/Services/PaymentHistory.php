<?php
namespace App\Services;

use App\Models\{User, WaterSubmission, WaterGatewayAttempt, XenditDemoAttempt};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

/** Read-only presentation across ledgers. Never combines financial totals. */
class PaymentHistory
{
    public function forUser(User $user, Request $request): LengthAwarePaginator
    {
        $request->validate(['view' => 'nullable|in:receipts', 'q' => 'nullable|string|max:100', 'method' => 'nullable|in:online,qr,cash',
            'status' => 'nullable|in:pending,processing,completed,failed,expired,rejected',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''), 'page' => 'nullable|integer|min:1']);
        $oversight = $user->hasPermission('payments.view');
        $rows = collect();
        $submissions = WaterSubmission::with('member.collection', 'member.receipt', 'member.user')
            ->when(!$oversight, fn($q) => $q->whereHas('member', fn($m) => $m->where('user_id', $user->id)));
        foreach ($submissions->lazyById(200) as $s) {
            $simulation = in_array($s->payment_method, ['xendit_test', 'paymongo_test'], true);
            $method = $simulation ? 'online' : ($s->payment_method === 'cash' ? 'cash' : 'qr');
            $receipt = $s->member->receipt;
            $rows->push(['key' => 'submission-'.$s->id, 'date' => $s->submitted_at ?? $s->created_at,
                'title' => $s->member->collection->title, 'faculty' => $s->member->user->full_name, 'amount' => $s->submitted_amount, 'method' => $method,
                'status' => match($s->status) {'approved' => 'completed', 'rejected' => 'rejected', default => 'pending'},
                'reference' => $s->reference_number ?? 'Submission #'.$s->id, 'simulation' => $simulation,
                'url' => route('water.submission', $s),
                'receipt' => $s->status === 'approved' && $s->member->status === 'paid' && $receipt && $receipt->water_submission_id === $s->id ? route('water.receipt', $receipt) : null]);
        }
        // General tests must never open the installed private demo ledger.
        if ((!app()->environment('testing') || config('database.connections.xendit_demo.database') === ':memory:')
            && Schema::connection('xendit_demo')->hasTable('demo_attempts')) {
            $demo = XenditDemoAttempt::where('source_database', config('xendit_demo.main_database'))
                ->when(!$oversight, fn($q) => $q->where('user_id', $user->id));
            foreach ($demo->lazyById(200) as $a) $rows->push($this->attempt($a, true));
        }
        if (Schema::hasTable('water_gateway_attempts')) {
            $attempts = WaterGatewayAttempt::with('member.collection')->whereNull('water_submission_id')
                ->when(!$oversight, fn($q) => $q->whereHas('member', fn($m) => $m->where('user_id', $user->id)));
            foreach ($attempts->lazyById(200) as $a) $rows->push($this->attempt($a, false));
        }
        $rows = $rows->filter(function ($row) use ($request) {
            return ($request->input('view') !== 'receipts' || $row['receipt'] !== null)
                && (!$request->filled('q') || mb_stripos($row['faculty'].' '.$row['title'].' '.$row['reference'], $request->string('q')->toString()) !== false)
                && (!$request->filled('method') || $row['method'] === $request->input('method'))
                && (!$request->filled('status') || $row['status'] === $request->input('status'))
                && (!$request->filled('from') || $row['date']->format('Y-m-d') >= $request->input('from'))
                && (!$request->filled('to') || $row['date']->format('Y-m-d') <= $request->input('to'));
        })->sort(fn($a, $b) => $b['date'] <=> $a['date'] ?: strcmp($b['key'], $a['key']))->values();
        $page = max(1, (int)$request->input('page', 1));
        return new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page,
            ['path' => $request->url(), 'query' => $request->query()]);
    }

    private function attempt($a, bool $demo): array
    {
        return ['key' => ($demo ? 'demo-' : 'online-').$a->id, 'date' => $a->created_at,
            'title' => $demo ? $a->contribution_title : $a->member->collection->title,
            'faculty' => $demo ? (User::find($a->user_id)?->full_name ?? 'Unavailable account') : $a->member->user->full_name, 'amount' => $a->amount_minor / 100,
            'method' => 'online', 'status' => match($a->status) {'confirmed' => 'completed', 'failed' => 'failed', 'expired' => 'expired', default => 'processing'},
            'reference' => $a->display_reference, 'simulation' => true,
            'url' => route($demo ? 'water.demo.show' : 'water.online.show', $a),
            'receipt' => $demo && $a->status === 'confirmed' ? route('water.demo.receipt', $a) : null];
    }
}
