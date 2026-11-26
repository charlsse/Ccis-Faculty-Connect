@extends('layouts.auth')
@section('title', 'Verify your email')
@section('content')
<div class="auth-card"><div class="auth-heading"><h2>Verify your official email</h2><span>Confirm ownership of your email before continuing account setup.</span></div>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@error('email')<p role="alert">{{ $message }}</p>@enderror
<form method="POST" action="{{ route('verification.send') }}">@csrf<button class="button button-primary">Send verification email</button></form>
<form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-secondary">Log out</button></form></div>
@endsection
