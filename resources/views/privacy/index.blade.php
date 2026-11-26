@extends('layouts.dashboard')
@section('title', 'Privacy & Security')
@section('content')
<div class="privacy-center">
    <section class="dashboard-hero content-hero"><div><p class="eyebrow">Trust &amp; documentation</p><h1>Privacy &amp; Security</h1><span>Clear publication status. Reviewed information. Responsible development.</span></div><a class="button button-secondary" href="{{ route('security.show') }}">Account security</a></section>
    <div class="privacy-overview fc-liquid-glass fc-glass-subtle"><div><span class="eyebrow">Policy acknowledgment</span><strong>{{ $acknowledgmentActive ? 'Enabled for approved policies' : 'Not active' }}</strong></div><p>{{ $acknowledgmentActive ? 'Account setup uses the published policy versions listed below.' : 'Account setup does not require acknowledgment of incomplete policy documents.' }}</p></div>
    @include('privacy.published-list')
    @include('privacy.references')
    @if($internal)
    <section class="privacy-internal" aria-labelledby="internal-heading"><div class="privacy-section-heading"><div><p class="eyebrow">Administrator access only</p><h2 id="internal-heading">Internal draft documentation</h2></div><span class="status-pill pending">Review required</span></div>
        <p>Working documents for review and capstone demonstration. These drafts are not approved policies, institutional authorization, or compliance attestations.</p>
        <div class="privacy-document-grid">@foreach($internal as $slug => $document)<a class="privacy-document-link" href="{{ route('privacy.draft', $slug) }}"><span><small>{{ $document['audience'] === 'public' ? 'Proposed user-facing policy' : 'Internal review document' }}</small><strong>{{ $document['title'] }}</strong><small>{{ $document['version'] }} · {{ ucfirst($document['status']) }}</small></span><span aria-hidden="true">&rarr;</span></a>@endforeach</div>
        <p class="privacy-reference-note">Publication requires documented review and an approved release. This interface cannot publish a draft or appoint a privacy contact.</p>
    </section>
    @endif
</div>
@endsection
