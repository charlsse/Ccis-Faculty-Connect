@extends('layouts.auth')

@section('title', 'Login')

@section('content')
    <div class="auth-card">
        @if (session('status'))
            <div class="auth-alert success" role="status">{{ session('status') }}</div>
        @endif
        <div class="auth-heading">
            @include('partials.brand', ['showText' => false, 'class' => 'auth-card-logo'])
            <p class="eyebrow">Secure Login</p>
            <h2>Welcome back to FacultyConnect</h2>
            <span>Use your UMak email, Faculty ID or assigned roster login ID to continue.</span>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="auth-form">
            @csrf
            <label>
                <span>Email, Faculty ID or roster login ID</span>
                <input type="text" name="login" value="{{ old('login') }}" autocomplete="username" required autofocus>
                @error('login')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <label>
                <span>Password</span>
                <div class="password-field">
                    <input id="login-password" type="password" name="password" autocomplete="current-password" required data-password-input>
                    <button type="button" data-password-toggle aria-controls="login-password" aria-pressed="false" aria-label="Show password">Show</button>
                </div>
                @error('password')<small class="field-error">{{ $message }}</small>@enderror
            </label>

            <a class="auth-help-link" href="{{ route('password.request') }}">Forgot your password?</a>

            <button class="button button-primary auth-submit" type="submit">Login</button>
        </form>

        <p class="auth-switch">Accounts are issued by a FacultyConnect administrator.</p>
    </div>
@endsection
