@extends('layouts.auth')
@section('title', 'Choose your password')
@section('content')
<div class="auth-card"><div class="auth-heading"><p class="eyebrow">Required account setup</p><h2>Choose your new password</h2><span>Use at least 12 characters with uppercase, lowercase, numbers, and symbols.</span></div>
<form method="POST" action="{{ route('onboarding.password.update') }}" class="auth-form" data-password-submit>@csrf
            @include('partials.new-password-fields')
            @include('privacy.acknowledgment')
<button class="button button-primary" type="submit">Replace temporary password</button></form>
<form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-secondary">Log out</button></form></div>
@endsection
