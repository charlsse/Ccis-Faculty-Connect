@extends('layouts.dashboard')

@section('title', $title)

@section('content')
    <section class="coming-soon">
        <div class="coming-icon">{{ strtoupper(substr($title, 0, 2)) }}</div>
        <p class="eyebrow">Phase 2 Module</p>
        <h1>{{ $title }}</h1>
        <p>This module is reserved for the next build phase. The completed dashboard areas are available while this feature is prepared.</p>
        <a class="button button-secondary" href="{{ route('dashboard') }}">Back to Dashboard</a>
    </section>
@endsection
