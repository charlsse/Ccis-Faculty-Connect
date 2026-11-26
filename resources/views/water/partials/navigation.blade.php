@php
    $user = auth()->user();
    $oversight = $user->hasPermission('payments.view');
    $manage = $user->isActive() && $user->hasPermission('payments.manage');
    $receipts = request()->routeIs('water.receipt', 'water.demo.receipt') || (request()->routeIs('water.demo.index') && request('view') === 'receipts');
    $transactions = request()->routeIs('water.demo.*', 'water.online.*', 'water.queue', 'water.submission', 'payments.*');
    $section = request()->routeIs('wallet.*') ? 'wallet'
        : (request()->routeIs('water.accounts*') ? 'settings'
        : (request()->routeIs('water.reports', 'water.report', 'water.audit') ? 'reports'
        : ($receipts ? ($oversight ? 'transactions' : 'receipts')
        : ($transactions ? 'transactions'
        : (($oversight && (request()->routeIs('water.show', 'water.create', 'water.edit', 'water.cash', 'water.checkout') || request('section') === 'collections' || request('category'))) ? 'collections' : 'overview')))));
    $items = $oversight ? [
        ['overview', $manage ? 'Overview' : 'Financial Overview', route('water.index'), 'layout-dashboard'],
        ['collections', 'Collections', route('water.index', ['section' => 'collections']), 'layers'],
        ['transactions', 'Transactions', route('water.demo.index'), 'arrow-left-right'],
        ['reports', 'Reports', route('water.reports'), 'chart-no-axes-combined'],
    ] : [
        ['overview', 'My Payments', route('water.index'), 'credit-card'],
        ['transactions', 'Transaction History', route('water.demo.index'), 'arrow-left-right'],
        ['receipts', 'Receipts', route('water.demo.index', ['view' => 'receipts']), 'receipt-text'],
    ];
    // Match the wallet controller's existing role and permission restrictions.
    if ($user->hasRole(['treasurer', 'admin', 'president']) && (config('wallet.enabled') || $oversight)) $items[] = ['wallet', 'Treasurer Wallet', route('wallet.index'), 'wallet'];
    if ($manage) $items[] = ['settings', 'Payment Settings', route('water.accounts'), 'settings-2'];
    $secondary = [];
    if ($section === 'collections' || (!$oversight && $section === 'overview')) {
        $secondary = [
            ['All collections', route('water.index', $oversight ? ['section' => 'collections'] : []), request()->routeIs('water.index') && !request('category'), 'layers'],
            ['Water Contribution', route('water.index', array_merge($oversight ? ['section' => 'collections'] : [], ['category' => 'water'])), request()->routeIs('water.index') && request('category') === 'water', 'droplet'],
            ['Other Collections', route('water.index', array_merge($oversight ? ['section' => 'collections'] : [], ['category' => 'other'])), request()->routeIs('water.index') && request('category') === 'other', 'folder'],
        ];
        if ($manage) $secondary[] = ['Create Collection', route('water.create'), request()->routeIs('water.create'), 'plus'];
    } elseif ($section === 'transactions') {
        $secondary = [
            ['All transactions', route('water.demo.index'), request()->routeIs('water.demo.index') && !request('status') && !request('view'), 'list'],
        ];
        if ($manage) $secondary[] = ['Pending Verification', route('water.queue'), request()->routeIs('water.queue'), 'clipboard-check'];
        if ($oversight) $secondary[] = ['Receipts', route('water.demo.index', ['view' => 'receipts']), $receipts, 'receipt-text'];
        $secondary[] = ['Historical Payment Records', route('payments.index'), request()->routeIs('payments.*'), 'archive'];
    } elseif ($section === 'reports') {
        $secondary[] = ['Collection reports & exports', route('water.reports'), request()->routeIs('water.reports', 'water.report'), 'chart-no-axes-combined'];
        if ($user->isAdmin()) $secondary[] = ['Audit trail', route('water.audit'), request()->routeIs('water.audit'), 'shield-check'];
    } elseif ($section === 'settings') {
        $secondary[] = ['Treasurer QR Accounts', route('water.accounts'), request()->routeIs('water.accounts*'), 'qr-code'];
    }
@endphp
<div class="payment-navigation">
    <nav class="payment-primary" aria-label="Payments & collections">
        @foreach($items as [$key, $label, $url, $icon])
        <a class="payment-nav-pill" href="{{ $url }}" @if($section === $key) aria-current="page" @endif>
            @include('water.partials.nav-icon', ['icon' => $icon])<span>{{ $label }}</span>
        </a>
        @endforeach
    </nav>
    @if($secondary)
    <nav class="payment-secondary" aria-label="{{ collect($items)->first(fn ($item) => $item[0] === $section)[1] ?? 'Payment' }} options">
        @foreach($secondary as [$label, $url, $active, $icon])
        <a href="{{ $url }}" @if($active) aria-current="page" @endif>@include('water.partials.nav-icon', ['icon' => $icon])<span>{{ $label }}</span></a>
        @endforeach
    </nav>
    @endif
</div>
