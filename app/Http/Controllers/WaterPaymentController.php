<?php

namespace App\Http\Controllers;

use App\Models\WaterCollection;
use App\Models\WaterMember;
use App\Models\WaterReceipt;
use App\Models\WaterSubmission;
use App\Services\WaterPaymentService;
use App\Services\WaterReceiptPdf;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WaterPaymentController extends Controller
{
    public function __construct(private WaterPaymentService $payments) {}

    public function checkout(Request $request, WaterMember $member)
    {
        abort_unless($member->user_id === $request->user()->id, 403);
        Gate::authorize('view', $member);
        $member->load('collection.account', 'latestSubmission', 'receipt');

        return view('water.checkout', [
            'member' => $member,
            'collection' => $member->collection,
            'onlineAvailable' => app(\App\Services\Payments\WaterGatewayService::class)->available(),
            'onlineAttempt' => \App\Services\Payments\WaterGatewayService::active($member),
            'qrAvailable' => $member->collection->account && Storage::disk('local')->exists($member->collection->account->qr_image_path),
        ]);
    }

    private function paymentData(Request $request): array
    {
        $data = $request->validate(['submitted_amount' => 'required|decimal:0,2|min:0.01|max:9999999999.99',
            'payment_date' => 'required|date_format:Y-m-d', 'payment_time' => 'required|date_format:H:i', 'confirmed' => 'accepted']);
        $data['paid_at'] = Carbon::createFromFormat('Y-m-d H:i', $data['payment_date'].' '.$data['payment_time'])->startOfMinute();
        $this->payments->require(! $data['paid_at']->isFuture(), 'The payment date and time cannot be in the future.');

        return $data;
    }

    public function submit(Request $request, WaterMember $member)
    {
        Gate::authorize('submit', $member);
        $data = $this->paymentData($request) + $request->validate([
            'reference_number' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]*$/'],
            'faculty_note' => 'nullable|string|max:2000',
            'proof' => 'required|file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:5120']);
        $path = $request->file('proof')->store('water/proofs', 'local');
        abort_unless($path, 503, 'Unable to store the proof. Please try again.');
        try {
            $this->payments->submit($member, $data + ['proof_path' => $path]);
        } catch (UniqueConstraintViolationException $e) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['reference_number' => 'This reference or payment attempt has already been submitted.']);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

        return redirect()->route('water.checkout', $member)->with('status', 'Payment submitted. Your proof has been sent to the Treasurer for verification.');
    }

    public function queue(Request $request)
    {
        Gate::authorize('manage', WaterCollection::class);
        $request->validate(['collection' => 'nullable|integer', 'status' => 'nullable|in:pending,approved,rejected', 'date' => 'nullable|date_format:Y-m-d', 'faculty' => 'nullable|string|max:100', 'reference' => 'nullable|string|max:100']);
        $query = WaterSubmission::with('member.user', 'member.collection')
            ->when($request->filled('collection'), fn ($q) => $q->whereHas('member', fn ($m) => $m->where('water_collection_id', $request->input('collection'))))
            ->where('status', $request->input('status') ?: 'pending')
            ->when($request->filled('date'), fn ($q) => $q->whereDate('submitted_at', $request->input('date')))
            ->when($request->filled('reference'), fn ($q) => $q->where('reference_lookup', WaterPaymentService::lookup($request->input('reference'))))
            ->when($request->filled('faculty'), fn ($q) => $q->whereHas('member.user', fn ($u) => $u->where(fn ($n) => $n->where('first_name', 'like', '%'.$request->input('faculty').'%')->orWhere('last_name', 'like', '%'.$request->input('faculty').'%')->orWhere('faculty_id', $request->input('faculty')))));

        return view('water.queue', ['submissions' => $query->latest('submitted_at')->paginate(20)->withQueryString(), 'collections' => WaterCollection::latest()->get(),
            'gatewayExceptions' => \Illuminate\Support\Facades\Schema::hasTable('water_gateway_attempts')
                ? \App\Models\WaterGatewayAttempt::whereIn('status', ['unknown', 'attention'])->with('member.user', 'member.collection')->latest()->limit(25)->get() : collect()]);
    }

    public function show(WaterSubmission $submission)
    {
        Gate::authorize('view', $submission->member);
        $submission->load('member.collection', 'member.user', 'member.submissions.verifier', 'member.receipt', 'verifier');

        return view('water.review', compact('submission'));
    }

    public function review(Request $request, WaterSubmission $submission)
    {
        Gate::authorize('manage', WaterCollection::class);
        $data = $request->validate(['decision' => 'required|in:approve,reject,correct', 'rejection_reason' => 'required_unless:decision,approve|nullable|string|max:2000', 'internal_verification_note' => 'nullable|string|max:2000', 'confirmed' => 'accepted']);
        $this->payments->review($submission, $data);

        return back()->with('status', 'Review saved. The faculty member has been notified.');
    }

    public function cashForm(WaterMember $member)
    {
        Gate::authorize('manage', WaterCollection::class);

        return view('water.cash', compact('member'));
    }

    public function cash(Request $request, WaterMember $member)
    {
        Gate::authorize('manage', WaterCollection::class);
        $data = $this->paymentData($request) + $request->validate(['internal_verification_note' => 'nullable|string|max:2000']);
        $this->payments->cash($member, $data);

        return redirect()->route('water.show', $member->water_collection_id)->with('status', 'Cash payment recorded and verified receipt generated.');
    }

    public function waive(Request $request, WaterMember $member)
    {
        Gate::authorize('manage', WaterCollection::class);
        $data = $request->validate(['reason' => 'required|string|max:2000', 'confirmed' => 'accepted']);
        $this->payments->waive($member, $data['reason']);

        return back()->with('status', 'Waiver recorded with the officer and reason.');
    }

    public function proof(Request $request, WaterSubmission $submission)
    {
        Gate::authorize('view', $submission->member);
        abort_unless($submission->proof_path && Storage::disk('local')->exists($submission->proof_path), 404);
        $ext = pathinfo($submission->proof_path, PATHINFO_EXTENSION);
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox"];

        // PDFs are attachment-only: never embed an uploaded document in the application origin.
        return ($ext === 'pdf' || $request->boolean('download'))
            ? Storage::disk('local')->download($submission->proof_path, 'payment-proof.'.$ext, $headers)
            : Storage::disk('local')->response($submission->proof_path, 'payment-proof.'.$ext, $headers);
    }

    public function receipt(Request $request, WaterReceipt $receipt)
    {
        Gate::authorize('view', $receipt->member);
        abort_unless($receipt->member->status === 'paid' && $receipt->submission->status === 'approved', 404);
        $this->payments->audit('receipt_viewed', $receipt->member, ['receipt_id' => $receipt->id]);
        if ($request->boolean('download')) {
            return response(app(WaterReceiptPdf::class)->render($receipt), 200, [
                'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$receipt->receipt_number.'.pdf"',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return view('water.receipt', compact('receipt'));
    }

    public static function csvCell($value): string
    {
        $value = (string) $value;

        return preg_match('/^[\s\x00-\x1F]*[=+@-]/u', $value) ? "'".$value : $value;
    }

    public function report(Request $request, WaterCollection $collection)
    {
        Gate::authorize('oversight', WaterCollection::class);
        if ($request->input('format') === 'xlsx') {
            $contents = app(\App\Services\CollectionReportExcel::class)->render($collection);
            $this->payments->audit('exported', $collection, ['format' => 'xlsx']);
            return response($contents, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="facultyconnect-collection-'.$collection->id.'.xlsx"',
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        }
        $summary = WaterCollectionController::summary($collection->members());
        if (! $request->boolean('download')) {
            return view('water.report', ['collection' => $collection, 'summary' => $summary, 'members' => $collection->members()->with('user', 'receipt', 'collection', 'latestSubmission')->paginate(30)]);
        }
        $this->payments->audit('exported', $collection);

        return response()->streamDownload(function () use ($collection, $summary) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Faculty', 'Assigned amount', 'Amount paid', 'Method', 'Reference', 'Status', 'Payment date', 'Verification date', 'Officer', 'Receipt']);
            foreach ($collection->members()->with('user', 'receipt', 'collection', 'latestSubmission')->lazyById(100) as $member) {
                $r = $member->receipt;
                $s = $member->latestSubmission;
                fputcsv($out, array_map([self::class, 'csvCell'], [$member->user->full_name, $member->assigned_amount, $r?->amount ?? '0.00', $r?->payment_method ?? $s?->payment_method, $r?->payment_reference ?? $s?->reference_number, $member->status_label, $r?->payment_date ?? $s?->paid_at, $r?->verified_at, $r?->snapshot['officer_name'] ?? '', $r?->receipt_number]));
            }
            fputcsv($out, []);
            foreach (['expected', 'collected', 'outstanding', 'waived', 'paid', 'pending', 'unpaid', 'rejected'] as $key) {
                fputcsv($out, [ucfirst($key), $summary[$key]]);
            }
            fputcsv($out, ['Timezone', config('app.timezone')]);
            fclose($out);
        }, 'water-collection-'.$collection->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
