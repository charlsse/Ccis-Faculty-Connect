@extends('layouts.dashboard')
@section('title', 'Water collections temporarily unavailable')
@section('content')<div class="water-module"><section class="water-card" role="alert"><h1>We could not complete this request</h1><p>Please reopen the collection to check its current status before trying again. Do not make another external payment because of this error.</p><a class="button" href="{{ route('water.index') }}">Return to water collections</a></section></div>@endsection
