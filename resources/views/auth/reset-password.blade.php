@extends('layouts.auth')

@section('title', 'Create New Password')

@section('content')
    <div class="auth-card verify-card">
        <div class="auth-heading">
            <p class="eyebrow">Account Security</p>
            <h2>Create a new password</h2>
            <span>Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</span>
        </div>
        <form method="POST" action="{{ route('password.update') }}" class="auth-form" data-password-submit>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label><span>Official email address</span><input type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required>@error('email') <small class="field-error">{{ $message }}</small> @enderror</label>
            @include('partials.new-password-fields')
            <button class="button button-primary auth-submit" type="submit">Reset Password</button>
        </form>
    </div>
@endsection
