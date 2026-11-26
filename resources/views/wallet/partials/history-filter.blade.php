<form method="get" class="wallet-filter" aria-label="Filter verified contributions">
<label>Collection<select name="collection"><option value="">All collections</option>@foreach($collections as $collection)<option value="{{ $collection->id }}" @selected((string)request('collection')===(string)$collection->id)>{{ $collection->title }}</option>@endforeach</select></label>
<label>Payment method<select name="method"><option value="">All methods</option>@foreach($methods as $method=>$total)<option value="{{ $method }}" @selected(request('method')===$method)>{{ $method }}</option>@endforeach</select></label>
<button>Apply filters</button><a href="{{ route('wallet.index') }}">Reset</a></form>
<p class="wallet-muted">{{ $receipts->total() }} verified receipts match. Summary totals above remain unfiltered. Attempts and submissions are not counted again.</p>
