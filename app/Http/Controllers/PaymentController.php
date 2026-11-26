<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Services\DataProtectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly DataProtectionService $dataProtection) {}

    public function index(Request $request): View
    {
        $query = Payment::query()->with(['member', 'recorder']);

        if (! $request->user()->hasPermission('payments.view')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $payments = $query->orderedForPortal()->paginate(10)->withQueryString();

        return view('dashboard.payments.index', [
            'payments' => $payments,
            'members' => User::query()
                ->select('id', 'first_name', 'middle_name', 'last_name', 'faculty_id')
                ->where('status', 'active')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'status' => $request->string('status')->toString(),
            'currentDate' => now()->format('F j, Y'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('payments.manage'), 403);

        $validated = $this->validatedData($request);
        $validated['recorded_by'] = $request->user()->id;
        $validated['paid_at'] = $validated['status'] === 'paid'
            ? ($request->filled('paid_at') ? $request->date('paid_at') : now())
            : null;

        Payment::create($validated);

        return back()->with('status', 'Collection record has been added.');
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('payments.manage'), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'paid', 'waived'])],
            'paid_at' => ['nullable', 'date'],
        ]);
        $validated['recorded_by'] = $request->user()->id;
        $validated['paid_at'] = $validated['status'] === 'paid'
            ? ($request->filled('paid_at') ? $request->date('paid_at') : now())
            : null;

        $payment->update($validated);

        return back()->with('status', 'Collection record has been updated.');
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('payments.manage'), 403);

        $payment->delete();

        return back()->with('status', 'Collection record has been removed.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reference_no' => [
                'required',
                'string',
                'max:80',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (Payment::query()->where(
                        'reference_no_lookup',
                        $this->dataProtection->paymentReferenceLookup((string) $value),
                    )->exists()) {
                        $fail('The reference number has already been taken.');
                    }
                },
            ],
            'description' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'status' => ['required', Rule::in(['pending', 'paid', 'waived'])],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
