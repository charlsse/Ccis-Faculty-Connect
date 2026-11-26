<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterMember;
use App\Models\WaterNotice;
use App\Models\WaterPaymentAccount;
use App\Models\WaterSubmission;
use App\Services\AuditLogger;
use App\Services\WaterPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class WaterCollectionController extends Controller
{
    public function __construct(private WaterPaymentService $payments) {}

    public static function summary($query): array
    {
        return ['expected' => (clone $query)->sum('assigned_amount'),
            'collected' => (clone $query)->where('status', 'paid')->sum('assigned_amount'),
            'outstanding' => (clone $query)->whereNotIn('status', ['paid', 'waived'])->sum('assigned_amount'),
            'waived' => (clone $query)->where('status', 'waived')->sum('assigned_amount'),
            'paid' => (clone $query)->where('status', 'paid')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'pending_amount' => (clone $query)->where('status', 'pending')->sum('assigned_amount'),
            'unpaid' => (clone $query)->where('status', 'unpaid')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
            'due' => (clone $query)->whereNotIn('water_members.status', ['paid', 'waived'])->join('water_collections', 'water_collections.id', '=', 'water_members.water_collection_id')->min('water_collections.due_date'),
            'latest' => (clone $query)->where('status', 'paid')->max('paid_at')];
    }

    public function index(Request $request)
    {
        $request->validate(['category' => 'nullable|in:water,other']);
        $collections = WaterCollection::visibleTo($request->user())
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->with(['members' => fn ($q) => $q->where('user_id', $request->user()->id)])->latest()->paginate(12)->withQueryString();
        $query = WaterMember::whereHas('collection', fn ($q) => $q->visibleTo($request->user()))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('collection', fn ($c) => $c->where('category', $request->input('category'))))
            ->when(! $request->user()->hasPermission('payments.view'), fn ($q) => $q->where('user_id', $request->user()->id));

        return view('water.index', ['collections' => $collections, 'summary' => self::summary($query),
            'recentPayments' => $request->user()->hasPermission('payments.view')
                ? WaterSubmission::with('member.collection')->latest('submitted_at')->limit(5)->get() : collect(),
            'personalWater' => WaterMember::where('user_id', $request->user()->id)->when($request->filled('category'), fn ($q) => $q->whereHas('collection', fn ($c) => $c->where('category', $request->input('category'))))->whereHas('collection', fn ($q) => $q->where('status', '!=', 'draft'))
                ->with('collection', 'latestSubmission', 'receipt')->orderByRaw("case when status in ('unpaid', 'rejected', 'pending') then 0 else 1 end")->latest('assigned_at')->get(),
            'notices' => WaterNotice::where('user_id', $request->user()->id)->whereHas('collection', fn ($q) => $q->visibleTo($request->user())->when($request->filled('category'), fn ($c) => $c->where('category', $request->input('category'))))->latest()->limit(15)->get()]);
    }

    public function create()
    {
        Gate::authorize('manage', WaterCollection::class);

        return $this->form(new WaterCollection);
    }

    private function form(WaterCollection $collection)
    {
        return view('water.form', ['collection' => $collection, 'accounts' => WaterPaymentAccount::where('is_active', true)->get(),
            'members' => User::where('status', 'active')->whereIn('role', ['faculty', 'admin', 'treasurer', 'secretary'])->orderBy('last_name')->get()]);
    }

    public function edit(WaterCollection $collection)
    {
        Gate::authorize('manage', WaterCollection::class);
        abort_unless($collection->status === 'draft' && ! $collection->published_at, 403, 'Only never-published drafts can be edited.');

        return $this->form($collection);
    }

    public function store(Request $request)
    {
        return $this->save($request, new WaterCollection);
    }

    public function update(Request $request, WaterCollection $collection)
    {
        return $this->save($request, $collection);
    }

    private function save(Request $request, WaterCollection $collection)
    {
        Gate::authorize('manage', WaterCollection::class);
        $data = $request->validate(['category' => 'sometimes|required|in:water,other', 'title' => 'required|string|max:160', 'description' => 'nullable|string|max:5000',
            'period' => 'required|date_format:Y-m', 'amount' => 'required|decimal:0,2|min:0.01|max:9999999999.99', 'due_date' => 'required|date_format:Y-m-d',
            'payment_account_id' => ['required', Rule::exists('water_payment_accounts', 'id')->where('is_active', true)],
            'assignment' => 'required|in:all,selected', 'members' => 'required_if:assignment,selected|array',
            'members.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('status', 'active')->whereIn('role', ['faculty', 'admin', 'treasurer', 'secretary'])]]);
        DB::transaction(function () use (&$collection, $data) {
            if ($collection->exists) {
                $collection = WaterCollection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
                $this->payments->require($collection->status === 'draft' && ! $collection->published_at, 'Published collection terms and assignments are immutable.');
            }
            $ids = $data['assignment'] === 'all'
                ? User::where('status', 'active')->whereIn('role', ['faculty', 'admin', 'treasurer', 'secretary'])->pluck('id')->all() : $data['members'];
            $this->payments->require(count($ids) > 0, 'Select at least one active member.');
            $collection->fill(collect($data)->only(['category', 'title', 'description', 'period', 'amount', 'due_date', 'payment_account_id'])->all());
            if (! $collection->exists) {
                $collection->created_by = auth()->id();
                $collection->status = 'draft';
            }
            $collection->save();
            $collection->members()->delete(); // Never-published draft assignments only, with no submissions.
            foreach ($ids as $id) {
                $collection->members()->create(['user_id' => $id, 'assigned_amount' => $data['amount'], 'assigned_at' => now(), 'status' => 'unpaid']);
            }
            $this->payments->audit('draft_saved', $collection, ['member_count' => count($ids)]);
        });

        return redirect()->route('water.show', $collection)->with('status', 'Draft saved. Publish it when ready.');
    }

    public function transition(Request $request, WaterCollection $collection)
    {
        Gate::authorize('manage', WaterCollection::class);
        $data = $request->validate(['status' => 'required|in:published,draft,closed,archived', 'confirmed' => 'accepted']);
        DB::transaction(function () use ($collection, $data) {
            $collection = WaterCollection::whereKey($collection->id)->lockForUpdate()->firstOrFail();
            $from = $collection->status;
            $allowed = ['draft' => ['published'], 'published' => ['draft', 'closed'], 'closed' => ['published', 'archived'], 'archived' => []];
            $this->payments->require(in_array($data['status'], $allowed[$from], true), 'This status transition is not allowed.');
            $submissions = $collection->members()->whereHas('submissions')->exists();
            if ($data['status'] === 'draft') {
                $this->payments->require(! $submissions && ! $collection->members()->where('status', 'waived')->exists(), 'A collection with financial activity cannot be unpublished. Close it instead.');
            }
            if ($data['status'] === 'archived') {
                $this->payments->require(! $collection->members()->whereNotIn('status', ['paid', 'waived'])->exists(), 'Resolve or authorize waivers for all obligations before archiving.');
            }
            if ($data['status'] === 'published') {
                $this->payments->require($collection->members()->exists(), 'The collection needs assigned members.');
                $this->payments->require($collection->account->is_active, 'The selected QR account is inactive. Create a new draft with the active account.');
                $collection->published_at ??= now();
            }
            $collection->status = $data['status'];
            $collection->closed_at = in_array($data['status'], ['closed', 'archived']) ? now() : null;
            $collection->save();
            $this->payments->audit('status_changed', $collection, ['from' => $from, 'to' => $collection->status]);
            if ($collection->status === 'published') {
                $collection->members()->with('user')->get()->each(fn ($m) => $this->payments->notify($m->user, $collection,
                    $collection->title.' ('.$collection->period.') is available. Assigned amount: PHP '.number_format((float) $m->assigned_amount, 2).'. Due '.$collection->due_date->format('F j, Y').'.',
                    'published:'.$collection->id.':'.$m->id.':'.hash('sha256', $collection->title.'|'.$collection->period.'|'.$m->assigned_amount.'|'.$collection->due_date->format('Y-m-d')),
                    ['title' => 'New Collection Published', 'route' => 'water.show', 'parameters' => [$collection->id]]));
            }
        });

        return back()->with('status', 'Collection status updated.');
    }

    public function show(Request $request, WaterCollection $collection)
    {
        Gate::authorize('view', $collection);
        $collection->load('account', 'creator');
        $query = $collection->members()->when(! $request->user()->hasPermission('payments.view'), fn ($q) => $q->where('user_id', $request->user()->id));

        $summary = self::summary(clone $query);
        $request->validate(['member_status' => 'nullable|in:unpaid,pending,paid,rejected,waived', 'search' => 'nullable|string|max:100', 'section' => 'nullable|in:overview,members']);
        $query->when($request->filled('member_status'), fn ($q) => $q->where('status', $request->input('member_status')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where(fn ($n) => $n->where('first_name', 'like', '%'.$request->input('search').'%')->orWhere('last_name', 'like', '%'.$request->input('search').'%')->orWhere('faculty_id', $request->input('search')))));

        return view('water.show', ['collection' => $collection, 'summary' => $summary,
            'personalMember' => $collection->members()->where('user_id', $request->user()->id)->first(),
            'members' => $query->with(['user', 'receipt', 'submissions.verifier', 'collection'])->paginate(25)->withQueryString()]);
    }

    public function reports()
    {
        Gate::authorize('oversight', WaterCollection::class);

        return view('water.reports', ['collections' => WaterCollection::latest()->paginate(20)]);
    }

    public function accounts()
    {
        Gate::authorize('manage', WaterCollection::class);

        return view('water.accounts', ['accounts' => WaterPaymentAccount::latest()->get()]);
    }

    public function storeAccount(Request $request)
    {
        Gate::authorize('manage', WaterCollection::class);
        $data = $request->validate(['label' => 'required|string|max:120', 'account_name' => 'required|string|max:160',
            'masked_account_number' => ['required', 'string', 'max:40', 'regex:/^[0-9xX*• +()-]+$/u', 'regex:/[xX*•]{3,}/u', 'not_regex:/\d{5}/'],
            'instructions' => 'nullable|string|max:3000', 'qr' => 'required|image|mimes:jpg,jpeg,png|extensions:jpg,jpeg,png|max:5120', 'confirmed' => 'accepted']);
        $path = $request->file('qr')->store('water/qr', 'local');
        abort_unless($path, 503, 'Unable to store the QR image. Please try again.');
        try {
            DB::transaction(function () use ($data, $path) {
                DB::table('water_settings')->where('id', 1)->lockForUpdate()->first();
                WaterPaymentAccount::query()->update(['is_active' => false]);
                $account = WaterPaymentAccount::create(collect($data)->only(['label', 'account_name', 'masked_account_number', 'instructions'])->all() + ['qr_image_path' => $path, 'created_by' => auth()->id(), 'is_active' => true]);
                DB::table('water_settings')->where('id', 1)->update(['active_account_id' => $account->id]);
                app(AuditLogger::class)->record('water.account_created', details: ['target_type' => 'WaterPaymentAccount', 'target_id' => $account->id]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return back()->with('status', 'New default QR account saved. Existing collection destinations are preserved.');
    }

    public function activateAccount(Request $request, WaterPaymentAccount $account)
    {
        Gate::authorize('manage', WaterCollection::class);
        $data = $request->validate(['active' => 'required|boolean', 'confirmed' => 'accepted']);
        DB::transaction(function () use ($account, $data) {
            DB::table('water_settings')->where('id', 1)->lockForUpdate()->first();
            if ($data['active']) {
                WaterPaymentAccount::query()->update(['is_active' => false]);
            }
            $account->update(['is_active' => (bool) $data['active']]);
            if ($data['active']) {
                DB::table('water_settings')->where('id', 1)->update(['active_account_id' => $account->id]);
            } else {
                DB::table('water_settings')->where('id', 1)->where('active_account_id', $account->id)->update(['active_account_id' => null]);
            }
            app(AuditLogger::class)->record('water.account_status_changed', details: ['target_type' => 'WaterPaymentAccount', 'target_id' => $account->id, 'active' => (bool) $data['active']]);
        });

        return back()->with('status', 'Account status updated. Historical destinations remain available on their collections.');
    }

    public function qr(Request $request, WaterCollection $collection)
    {
        Gate::authorize('view', $collection);

        return $this->image($request, $collection->account);
    }

    public function accountQr(Request $request, WaterPaymentAccount $account)
    {
        Gate::authorize('manage', WaterCollection::class);

        return $this->image($request, $account);
    }

    private function image(Request $request, WaterPaymentAccount $account)
    {
        abort_unless(Storage::disk('local')->exists($account->qr_image_path), 404);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"];
        $name = 'official-payment-qr.'.pathinfo($account->qr_image_path, PATHINFO_EXTENSION);

        return $request->boolean('download') ? Storage::disk('local')->download($account->qr_image_path, $name, $headers) : Storage::disk('local')->response($account->qr_image_path, $name, $headers);
    }

    public function audit(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $request->validate(['date' => 'nullable|date_format:Y-m-d', 'action' => 'nullable|string|max:100', 'actor' => 'nullable|integer|min:1']);

        return view('water.audit', ['logs' => AuditLog::where('action', 'like', 'water.%')
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->input('date')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('actor'), fn ($q) => $q->where('actor_user_id', $request->input('actor')))
            ->latest('created_at')->paginate(50)->withQueryString()]);
    }

    public function readNotice(WaterNotice $notice)
    {
        abort_unless($notice->user_id === auth()->id(), 403);
        $notice->update(['read_at' => now()]);

        return back();
    }
}
