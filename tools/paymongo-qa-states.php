<?php

// Isolated browser fixtures only. No provider request is sent.
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/paymongo-qa-environment.php';
waterBrowserEnvironment();
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = dirname(__DIR__).'/storage/app/private/paymongo-qa';
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $directory.'/database.sqlite') throw new RuntimeException('QA isolation guard failed.');
if (file_exists($directory.'/states.json')) throw new RuntimeException('Fixture state already exists.');
\Illuminate\Support\Facades\Http::preventStrayRequests();
$users = json_decode(file_get_contents($directory.'/accounts.json'), true);
$account = \App\Models\WaterPaymentAccount::create(['label' => 'Synthetic QR destination', 'account_name' => 'Synthetic club', 'masked_account_number' => '***1234', 'qr_image_path' => 'synthetic-fixture-not-a-payment-qr.png', 'created_by' => $users['treasurer']['id'], 'is_active' => true]);
$states = [];
foreach (['available', 'pending', 'unknown', 'attention', 'expired', 'confirmed'] as $state) {
    $c = \App\Models\WaterCollection::create(['title' => 'QA '.ucfirst($state).' Water Contribution', 'period' => now()->format('Y-m'), 'amount' => '500.00', 'due_date' => now()->addWeek(), 'status' => 'published', 'published_at' => now(), 'payment_account_id' => $account->id, 'created_by' => $users['treasurer']['id']]);
    $m = $c->members()->create(['user_id' => $users['faculty']['id'], 'assigned_amount' => '500.00', 'assigned_at' => now(), 'status' => 'unpaid']);
    $states[$state] = ['checkout' => route('water.checkout', $m), 'collection' => route('water.show', $c)];
    if ($state === 'available') continue;
    $attempt = \App\Models\WaterGatewayAttempt::create(['reference' => (string) \Illuminate\Support\Str::uuid(), 'water_member_id' => $m->id, 'active_member_id' => $state === 'expired' ? null : $m->id,
        'amount_minor' => 50000, 'status' => $state === 'confirmed' ? 'pending' : $state, 'provider_session_id' => $state === 'unknown' ? null : 'cs_'.$state,
        'checkout_url' => $state === 'pending' ? 'https://checkout.paymongo.com/syntheticFixtureOnly' : null]);
    $states[$state]['status'] = route('water.online.show', $attempt);
    if ($state === 'confirmed') {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['data' => ['id' => 'cs_confirmed', 'type' => 'checkout_session', 'attributes' => [
            'livemode' => false, 'reference_number' => $attempt->display_reference, 'status' => 'active', 'line_items' => [['amount' => 50000, 'quantity' => 1, 'currency' => 'PHP']],
            'payments' => [['id' => 'pay_synthetic', 'attributes' => ['status' => 'paid', 'amount' => 50000, 'currency' => 'PHP', 'livemode' => false, 'source' => ['type' => 'qrph'], 'paid_at' => now()->timestamp]]],
        ]]])]);
        app(\App\Services\Payments\WaterGatewayService::class)->reconcile('cs_confirmed');
        $states[$state]['receipt'] = route('water.receipt', $m->receipt()->firstOrFail());
    }
}
file_put_contents($directory.'/states.json', json_encode($states));
echo "Prepared synthetic browser states through the adapter's HTTP mock. No PayMongo sandbox or live request was made.\n";
