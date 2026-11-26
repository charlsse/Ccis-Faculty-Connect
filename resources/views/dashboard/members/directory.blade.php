@extends('layouts.dashboard')
@section('title', 'Faculty Directory')
@section('content')
<section class="dashboard-hero"><div><p class="eyebrow">Club directory</p><h1>Faculty Directory</h1><span>Official work information</span></div></section>
<section class="portal-panel">
<form method="GET" class="member-search"><label>Search name or department<input name="search" type="search" value="{{ request('search') }}"></label><button class="button button-primary">Search</button></form>
<div class="table-wrap"><table><thead><tr><th>Name</th><th>Official email</th><th>Department</th><th>Position</th></tr></thead><tbody>
@foreach($members as $member)<tr><td>{{ $member->full_name }}</td><td>{{ $member->email }}</td><td>{{ $member->department }}</td><td>{{ $member->position }}</td></tr>@endforeach
</tbody></table></div>{{ $members->links() }}</section>
@endsection
