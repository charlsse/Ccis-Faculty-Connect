@extends('layouts.dashboard')

@section('title', 'Create User Account')

@section('content')
    <section class="dashboard-hero content-hero">
        <div><p class="eyebrow">Administrator Tools</p><h1>Create User Account</h1><span>Send temporary credentials when ready. The member replaces the password at first login.</span></div>
        <a class="button button-secondary" href="{{ route('members.index') }}">Back to Accounts</a>
    </section>
    @if ($errors->any())<div class="dashboard-alert error" role="alert">Please review the highlighted fields.</div>@endif
    <section class="content-editor-section">
        <form class="announcement-form" method="POST" action="{{ route('members.store') }}">
            @csrf
            @include('dashboard.members.partials.form', ['member' => new \App\Models\User(['status' => 'pending_activation', 'role' => 'faculty'])])
            <div class="member-note">Unsent accounts cannot sign in. Sending an invitation does not activate the account.</div>
            <div class="form-actions"><button class="button button-secondary" type="submit">Create Account</button><button class="button button-primary" type="submit" name="send_invitation" value="1">Create &amp; Send Invitation</button></div>
        </form>
    </section>
@endsection
