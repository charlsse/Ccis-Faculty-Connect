@extends('layouts.app')
@section('title', 'Privacy & Policy Information — FacultyConnect')
@section('bodyClass', 'fc-public privacy-page')
@push('styles')<link rel="stylesheet" href="{{ asset('css/privacy.css') }}">@endpush
@section('content')
<a class="skip-link" href="#privacy-content">Skip to content</a>
<header class="privacy-public-nav fc-liquid-glass fc-glass-clear">@include('partials.brand')<div><a href="{{ route('landing') }}">Home</a><a href="{{ route('login') }}">Sign in</a>@include('partials.theme-toggle')</div></header>
<main class="privacy-center privacy-public-content" id="privacy-content"><p class="eyebrow">Documentation status</p><h1>Privacy &amp; policy information</h1><p>Review the current policy publication status and development references.</p>@include('privacy.published-list')@include('privacy.references')</main>
@endsection
