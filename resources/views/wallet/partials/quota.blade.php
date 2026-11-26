@php($target=$targets->get($c->id))
@php($collected=$receiptTotals->get($c->id,0))
<div class="wallet-collection"><div class="wallet-row"><span>Verified {{ $money($collected) }}</span><span>{{ $target ? 'Target '.$money($target->amount_minor) : 'Target not configured' }}</span></div>
@if($target)<progress max="{{ $target->amount_minor }}" value="{{ min($collected,$target->amount_minor) }}" aria-label="Collection target progress"></progress><small>{{ $collected >= $target->amount_minor ? 'Target reached; withdrawal still requires provider approval and available funds.' : 'Collection target in progress.' }}</small>@endif
@if(auth()->user()->role==='treasurer')
@if($targetsReady)<details><summary>Set collection target</summary><form class="wallet-form" method="post" action="{{ route('wallet.target') }}">@csrf<input type="hidden" name="collection" value="{{ $c->id }}"><input type="hidden" name="revision" value="{{ $target->revision??0 }}"><input type="hidden" name="operation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><label>Target (PHP)<input name="amount" inputmode="decimal" value="{{ $target?number_format($target->amount_minor/100,2,'.',''):'' }}" required></label><button>Save target</button></form></details>
@else<p class="wallet-muted">Target editing awaits approved database setup.</p>@endif
@endif</div>
