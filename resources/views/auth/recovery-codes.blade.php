@extends('layouts.auth')

@section('title', 'Save Recovery Codes')

@section('content')
    <div class="auth-card verify-card">
        <div class="auth-heading">
            <p class="eyebrow">One-Time Recovery Codes</p>
            <h2>Save these codes now</h2>
            <span>Each code works once if your authenticator is unavailable. They will not be shown again.</span>
        </div>
        <div class="recovery-code-grid" aria-label="Recovery codes">
            @foreach ($codes as $code)<code>{{ $code }}</code>@endforeach
        </div>
        <p class="verify-note">Store these codes in a password manager or another secure offline location. Never send them by email or share them.</p>
        <a class="button button-primary auth-submit" href="{{ route('dashboard') }}">I Have Saved My Codes</a>
    </div>
@endsection
