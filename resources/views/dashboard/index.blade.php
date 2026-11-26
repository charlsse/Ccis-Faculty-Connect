@extends('layouts.dashboard')

@section('title', $user->isAdmin() ? 'Administrator Dashboard' : 'Faculty Dashboard')

@section('content')


    <section class="dashboard-hero workspace-welcome" data-dot-section>
        <div class="fc-microdots fc-microdots--accent" data-microdots="accent" aria-hidden="true"><canvas aria-hidden="true"></canvas></div>
        <div>
            <p class="eyebrow">{{ $user->isAdmin() ? 'Administration / Overview' : 'Your faculty workspace' }}</p>
            <h1>Welcome back, {{ $user->first_name }}!</h1>
            <span>{{ $user->department }} - {{ $user->position }}</span>
        </div>
        <a class="button button-primary" href="{{ route('profile.show') }}">View Profile</a>
    </section>

    <section class="summary-grid" aria-label="Dashboard summary">
        <article class="summary-card">
            <span>{{ $user->hasPermission('payments.view') ? 'Collected This Month' : 'Paid This Month' }}</span>
            <strong>&#8369;{{ number_format($currentMonthPaid, 2) }}</strong>
            <small>Based on paid records</small>
        </article>
        <article class="summary-card success">
            <span>{{ $user->hasPermission('payments.view') ? 'Active Members' : 'Open Balances' }}</span>
            <strong>{{ $user->hasPermission('payments.view') ? $activeMembers : $pendingPayments }}</strong>
            <small>{{ $user->hasPermission('payments.view') ? 'Faculty accounts' : 'Pending records' }}</small>
        </article>
        <article class="summary-card warning">
            <span>Published Announcements</span>
            <strong>{{ $publishedAnnouncements }}</strong>
            <small>Available faculty updates</small>
        </article>
        <article class="summary-card">
            <span>{{ $user->hasPermission('payments.view') ? 'Collection Records' : 'My Records' }}</span>
            <strong>{{ $paymentRecords }}</strong>
            <small>{{ $user->hasPermission('payments.view') ? 'Tracked contributions' : 'Your payment entries' }}</small>
        </article>
    </section>

    @include('dashboard.partials.personal-water')

    @include('dashboard.partials.announcement-preview')

    <section class="water-module" aria-label="Water collections">
        <div class="section-heading"><h2>{{ $user->hasPermission('payments.view') ? 'Club Collections' : 'My Contribution Summary' }}</h2><a href="{{ route('water.index') }}">View collections & payment history</a></div>
        @include('water.partials.summary', ['summary' => $waterSummary])
    </section>

    <section class="quick-actions">
        <div class="section-heading compact">
            <p class="eyebrow">Quick Actions</p>
            <h2>{{ $user->isAdmin() ? 'Manage your community' : 'Your everyday essentials' }}</h2>
        </div>
        <div class="action-grid">
            <a href="{{ route('profile.show') }}">View Profile</a>
            <a href="{{ route('water.index') }}">Payments &amp; Collections</a>
            <a href="{{ route('announcements.index') }}">Read Announcements</a>
            @if ($user->isAdmin())
                <a href="{{ route('members.index') }}">Manage Members</a>
            @else
                <a href="{{ route('profile.show') }}">Update Faculty Record</a>
            @endif
        </div>
    </section>

    <div class="fc-transactions-section">


        <section class="portal-panel">
            <div class="panel-heading">
                <h2>Recent Transactions</h2>
                <a href="{{ route('payments.index') }}">Details</a>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            <tr>
                                <td data-label="Date">{{ optional($payment->paid_at ?? $payment->due_date)->format('M. j, Y') ?? 'No date' }}</td>
                                <td data-label="Description">
                                    {{ $payment->description }}
                                    @if ($user->hasPermission('payments.view') && $payment->member)
                                        <br><small>{{ $payment->member->full_name }}</small>
                                    @endif
                                </td>
                                <td data-label="Amount">&#8369;{{ number_format($payment->amount, 2) }}</td>
                                <td data-label="Status"><span class="status-pill {{ $payment->status }}">{{ $payment->status_label }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td class="empty-table-cell" colspan="4">No collection records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection


