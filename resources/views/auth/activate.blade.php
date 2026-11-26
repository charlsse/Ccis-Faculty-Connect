@extends('layouts.auth')

@section('title', 'Activate Account')

@section('content')
    <div class="auth-card verify-card">
        <div class="auth-heading">
            <p class="eyebrow">Account Invitation</p>
            <h2>Welcome, {{ $user->first_name }}</h2>
            <span>Confirm the FacultyConnect account for <strong>{{ $user->email }}</strong> by creating your password. Two-factor setup follows next.</span>
        </div>
        <form method="POST" action="{{ route('activation.store', $user) }}" class="auth-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label><span>New password</span><input type="password" name="password" autocomplete="new-password" required autofocus>@error('password') <small class="field-error">{{ $message }}</small> @enderror</label>
            <label><span>Confirm password</span><input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            <div class="member-note">Use at least 12 characters with uppercase, lowercase, a number, and a symbol. Never share your password or invitation link.</div>
            <button class="button button-primary auth-submit" type="submit">Create Password &amp; Continue</button>
        </form>
    </div>
@endsection
