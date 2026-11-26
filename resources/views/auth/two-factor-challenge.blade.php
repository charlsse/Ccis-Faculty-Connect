@extends('layouts.auth')

@section('title', 'Two-Factor Authentication')

@section('content')
    <div class="auth-card verify-card">
        <div class="auth-heading">
            <p class="eyebrow">Second Step</p>
            <h2>Enter your authentication code</h2>
            <span>Open your authenticator app and enter the current six-digit code. You may enter one unused recovery code instead.</span>
        </div>
        <form method="POST" action="{{ route('two-factor.challenge.store') }}" class="auth-form">
            @csrf
            <label><span>Authentication or recovery code</span><input class="otp-input" type="text" inputmode="numeric" name="code" autocomplete="one-time-code" maxlength="32" required autofocus>@error('code') <small class="field-error">{{ $message }}</small> @enderror</label>
            <label class="check-row trusted-device-check">
                <input type="checkbox" name="trust_device" value="1" @checked(old('trust_device'))>
                <span>Trust this device</span>
            </label>
            <p class="verify-note trust-warning">Leave this unchecked on shared, public, or borrowed computers.</p>
            <button class="button button-primary auth-submit" type="submit">Verify &amp; Sign In</button>
        </form>
        <p class="auth-switch"><a href="{{ route('login') }}">Start over</a></p>
    </div>
<p class="verify-note">Browser trust requires an authenticator code. Recovery-code sign-ins do not create new trust.</p>
@endsection
