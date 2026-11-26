@extends('layouts.dashboard')

@section('title', 'User Accounts')

@section('content')
    <section class="dashboard-hero content-hero">
        <div><p class="eyebrow">Administrator Tools</p><h1>User Accounts</h1><span>Create, search, filter, and secure FacultyConnect access.</span></div>
        <a class="button button-secondary" href="{{ route('members.create') }}">Create Account</a>
    </section>

    @if (session('status')) <div class="dashboard-alert success" role="status">{{ session('status') }}</div> @endif
    @if (session('warning')) <div class="dashboard-alert error" role="alert">{{ session('warning') }}</div> @endif

    <section class="portal-panel">
        <form class="member-search account-filters" method="GET" action="{{ route('members.index') }}">
            <label>Search users<input type="search" name="search" value="{{ $search }}" placeholder="Name, ID, email, department, or position"></label>
            <label>Role<select name="role"><option value="">All roles</option>@foreach(config('roles') as $value => $settings)<option value="{{ $value }}" @selected($roleFilter === $value)>{{ $settings['label'] }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="">All statuses</option><option value="pending_activation" @selected($statusFilter === 'pending_activation')>Pending Activation</option><option value="active" @selected($statusFilter === 'active')>Active</option><option value="deactivated" @selected($statusFilter === 'deactivated')>Deactivated</option></select></label>
            <button class="button button-primary" type="submit">Apply</button>
            @if ($search || $roleFilter || $statusFilter)<a class="button button-secondary" href="{{ route('members.index') }}">Clear</a>@endif
        </form>

        <div class="table-wrap">
            <table class="member-table">
                <thead><tr><th>User</th><th>Role</th><th>Department / Position</th><th>Status</th><th>Created / Last access</th><th></th></tr></thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td data-label="User"><strong>{{ $member->full_name }}</strong><span>{{ $member->faculty_id }} · {{ $member->email }}</span></td>
                            <td data-label="Role">{{ $member->role_label }}</td>
                            <td data-label="Department"><strong>{{ $member->department }}</strong><span>{{ $member->position }}</span></td>
                            <td data-label="Status"><span class="status-pill {{ $member->status === 'active' ? 'normal' : ($member->status === 'pending_activation' ? 'pending' : 'urgent') }}">{{ str($member->status)->replace('_', ' ')->title() }}</span></td>
                            <td data-label="Activity"><strong>{{ $member->created_at->format('M j, Y') }}</strong><span>{{ $member->last_login_at?->diffForHumans() ?? 'Never accessed' }}</span></td>
                            <td data-label="Action"><a class="table-link" href="{{ route('members.show', $member) }}">Manage</a></td>
                        </tr>
                    @empty
                        <tr><td class="empty-table-cell" colspan="6">No user accounts matched these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($members->hasPages())
        <nav class="simple-pagination" aria-label="User account pagination">
            @if ($members->onFirstPage())<span>Previous</span>@else<a href="{{ $members->previousPageUrl() }}">Previous</a>@endif
            <strong>Page {{ $members->currentPage() }} of {{ $members->lastPage() }}</strong>
            @if ($members->hasMorePages())<a href="{{ $members->nextPageUrl() }}">Next</a>@else<span>Next</span>@endif
        </nav>
    @endif
@endsection
