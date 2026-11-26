@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
    <div class="auth-card verify-card">
        <div class="auth-heading">
            @include('partials.brand', ['showText' => false, 'class' => 'auth-card-logo'])
            <p class="eyebrow">Account Recovery</p>
            <h2>Reset your password</h2>
            <span>Enter your official email. For privacy, the response is the same whether or not an active account exists.</span>
        </div>
        @if (session('status')) <div class="auth-alert success" role="status">{{ session('status') }}</div> @endif
        <form method="POST" action="{{ route('password.email') }}" class="auth-form">
            @csrf
            <label><span>Official email address</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>@error('email') <small class="field-error">{{ $message }}</small> @enderror</label>
            <button class="button button-primary auth-submit" type="submit">Send Reset Link</button>
        </form>
        <p class="auth-switch"><a href="{{ route('login') }}">Return to login</a></p>
    </div>
@endsection
