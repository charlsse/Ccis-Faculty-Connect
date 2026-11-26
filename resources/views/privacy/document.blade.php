@extends($internal ? 'layouts.dashboard' : 'layouts.app')
@section('title', $document['title'])
@section('bodyClass', 'fc-public privacy-page')
@push('styles')<link rel="stylesheet" href="{{ asset('css/privacy.css') }}">@endpush
@section('content')
<div class="privacy-center {{ $internal ? '' : 'privacy-public-content' }}">
    <a class="button button-secondary" href="{{ route($internal ? 'privacy.index' : 'privacy.public') }}">&larr; Documentation</a>
    <header class="privacy-document-heading"><p class="eyebrow">{{ $internal ? 'Administrator review copy' : 'Published policy' }}</p><h1>{{ $document['title'] }}</h1><p>Version {{ $document['version'] }} · {{ $internal ? 'Internal documentation — not for public distribution' : 'Effective '.\Illuminate\Support\Carbon::parse($document['approval']['effective_at'])->toDateString() }}</p></header>
    @if($internal)<div class="privacy-draft-banner" role="note"><strong>{{ $document['status'] === 'draft' ? 'Draft — approval required' : 'Internal review copy' }}</strong><span>Distinguish verified implementation evidence from proposed policies. No legal compliance, university approval or ISO certification is asserted.</span></div>@endif
    @if(str_contains($html, '<table'))<p class="privacy-reference-note">Wide tables scroll horizontally to show all columns.</p>@endif
    <article class="privacy-prose">{!! $html !!}</article>
    <p class="privacy-fingerprint">Document SHA-256 <code>{{ $document['sha256'] }}</code></p>
</div>
@endsection
