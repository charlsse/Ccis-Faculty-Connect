@extends('layouts.dashboard')
@section('title', 'Collection reports')
@section('content')<div class="water-module">
@include('water.partials.header', ['heading' => 'Collection reports'])
<p>Open a collection report to review verified totals and export its payment records.</p>
<section class="water-grid">@forelse($collections as $collection)<article class="water-card"><h2>{{ $collection->title }}</h2><p>{{ $collection->period }} · {{ ucfirst($collection->status) }}</p><a class="button" href="{{ route('water.report', $collection) }}">Report & Excel export</a></article>@empty<p class="water-card">No collection reports yet.</p>@endforelse</section>
{{ $collections->links() }}
</div>@endsection
