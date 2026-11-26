@extends('layouts.dashboard')

@section('title', 'Payment Records')

@section('content')
    <section class="dashboard-hero content-hero">
        <div>
            <p class="eyebrow">CCIS Faculty Club</p>
            <h1>Payment Records</h1>
            <span>{{ auth()->user()->hasPermission('payments.manage') ? 'Record and monitor faculty club collections.' : 'Review your contribution records and payment status.' }}</span>
        </div>
        @if (auth()->user()->hasPermission('payments.manage'))
            <a class="button button-secondary" href="{{ route('payments.index', ['add' => 1]) }}#new-payment">Add Manual Record</a>
        @endif
    </section>

    @include('water.partials.navigation')
    <p>Historical contribution records entered by the Treasurer. New Water and Other Collections use QR payment, proof and receipts under <a href="{{ route('water.index') }}">Overview</a>.</p>

    @if (session('status'))
        <div class="dashboard-alert success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="dashboard-alert error" role="alert">Please review the highlighted fields and try again.</div>
    @endif

    @if (auth()->user()->hasPermission('payments.manage') && (request()->boolean('add') || $errors->any()))
        <section class="content-editor-section" id="new-payment">
            <div class="editor-section-heading">
                <p class="eyebrow">Payment Record Management</p>
                <h2>Add manual record</h2>
            </div>

            <form class="announcement-form" method="POST" action="{{ route('payments.store') }}">
                @csrf
                <div class="form-columns">
                    <label>
                        Faculty member
                        <select name="user_id" required>
                            <option value="">Select member</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected(old('user_id') == $member->id)>{{ $member->full_name }} - {{ $member->faculty_id }}</option>
                            @endforeach
                        </select>
                        @error('user_id') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Reference no.
                        <input type="text" name="reference_no" value="{{ old('reference_no', 'FC-PAY-'.now()->format('Ymd-His')) }}" autocomplete="off" required>
                        @error('reference_no') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Description
                        <input type="text" name="description" value="{{ old('description', 'Monthly Contribution') }}" required>
                        @error('description') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Amount
                        <input type="number" name="amount" value="{{ old('amount', '500.00') }}" min="0.01" step="0.01" required>
                        @error('amount') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Status
                        <select name="status" required>
                            @foreach (['pending' => 'Pending', 'paid' => 'Paid', 'waived' => 'Waived'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'pending') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Due date
                        <input type="date" name="due_date" value="{{ old('due_date', now()->endOfMonth()->toDateString()) }}">
                        @error('due_date') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <label>
                        Paid date
                        <input type="datetime-local" name="paid_at" value="{{ old('paid_at') }}">
                        @error('paid_at') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                </div>

                <label>
                    Notes
                    <textarea name="notes" rows="3" autocomplete="off">{{ old('notes') }}</textarea>
                    @error('notes') <span class="field-error">{{ $message }}</span> @enderror
                </label>

                <div class="form-actions">
                    <button class="button button-primary" type="submit">Save Collection Record</button>
                </div>
            </form>
        </section>
    @endif

    <section class="portal-panel">
        <div class="panel-heading">
            <h2>{{ auth()->user()->hasPermission('payments.view') ? 'All collection records' : 'My payment history' }}</h2>
            <form class="status-filter" method="GET" action="{{ route('payments.index') }}">
                <select name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (['pending' => 'Pending', 'paid' => 'Paid', 'waived' => 'Waived'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="table-wrap">
            <table class="member-table">
                <thead>
                    <tr>
                        @if (auth()->user()->hasPermission('payments.view'))
                            <th>Faculty</th>
                        @endif
                        <th>Reference</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Due</th>
                        <th>Status</th>
                        @if (auth()->user()->hasPermission('payments.manage'))
                            <th>Update</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            @if (auth()->user()->hasPermission('payments.view'))
                                <td data-label="Faculty">
                                    <strong>{{ $payment->member->full_name }}</strong>
                                    <span>{{ $payment->member->faculty_id }}</span>
                                </td>
                            @endif
                            <td data-label="Reference">{{ $payment->reference_no }}</td>
                            <td data-label="Description">
                                {{ $payment->description }}
                                @if ($payment->notes)
                                    <br><small>{{ $payment->notes }}</small>
                                @endif
                            </td>
                            <td data-label="Amount">&#8369;{{ number_format($payment->amount, 2) }}</td>
                            <td data-label="Due">{{ optional($payment->due_date)->format('M. j, Y') ?? 'No due date' }}</td>
                            <td data-label="Status"><span class="status-pill {{ $payment->status }}">{{ $payment->status_label }}</span></td>
                            @if (auth()->user()->hasPermission('payments.manage'))
                                <td data-label="Update">
                                    <form class="inline-payment-form" method="POST" action="{{ route('payments.update', $payment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status">
                                            @foreach (['pending' => 'Pending', 'paid' => 'Paid', 'waived' => 'Waived'] as $value => $label)
                                                <option value="{{ $value }}" @selected($payment->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit">Save</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td class="empty-table-cell" colspan="{{ auth()->user()->hasPermission('payments.manage') ? 7 : 5 }}">No collection records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($payments->hasPages())
        <nav class="simple-pagination" aria-label="Payment pagination">
            @if ($payments->onFirstPage())
                <span>Previous</span>
            @else
                <a href="{{ $payments->previousPageUrl() }}">Previous</a>
            @endif

            <strong>Page {{ $payments->currentPage() }} of {{ $payments->lastPage() }}</strong>

            @if ($payments->hasMorePages())
                <a href="{{ $payments->nextPageUrl() }}">Next</a>
            @else
                <span>Next</span>
            @endif
        </nav>
    @endif
@endsection
