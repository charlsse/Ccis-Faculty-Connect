@extends('layouts.auth')

@section('title', 'Set Up Two-Factor Authentication')

@section('content')
    <div class="auth-card verify-card">
        <div class="auth-heading">
            <p class="eyebrow">Required Security Setup</p>
            <h2>Protect your account with 2FA</h2>
            <span>Scan this QR code using Google Authenticator, Microsoft Authenticator, Authy, or another TOTP app.</span>
        </div>
        <div class="two-factor-setup">
            <img class="qr-code" src="{{ $qrCode }}" alt="QR code for adding FacultyConnect to an authenticator app">
            <div class="manual-key"><span>Manual setup key</span><code id="manualSetupKey">{{ $user->two_factor_secret }}</code><button type="button" class="button button-secondary" data-copy-setup-key>Copy setup key</button></div>
        </div>
        <form method="POST" action="{{ route('two-factor.confirm') }}" class="auth-form">
            @csrf
            <label><span>Six-digit authentication code</span><input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" name="code" autocomplete="one-time-code" required autofocus>@error('code') <small class="field-error">{{ $message }}</small> @enderror</label>
            <label class="check-row trusted-device-check"><input type="checkbox" name="trust_device" value="1"><span>Trust this device</span></label>
            <p class="verify-note trust-warning">Use only on a private browser you control.</p>
            <button class="button button-primary auth-submit" type="submit">Confirm Two-Factor Setup</button>
        </form>
        <p class="verify-note">The setup key and future one-time codes are sensitive. Do not share them. Recovery codes will be shown once after confirmation.</p>
    </div>
@endsection
