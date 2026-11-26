<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/treasurer-qr-environment.php';
waterBrowserEnvironment();
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = dirname(__DIR__).'/storage/app/private/treasurer-qr-qa';
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $directory.'/database.sqlite') throw new RuntimeException('QA isolation guard failed.');
if (file_exists($directory.'/states.json')) throw new RuntimeException('Fixtures already exist.');
if (\Illuminate\Support\Facades\Schema::hasTable('water_gateway_attempts')) throw new RuntimeException('Normal QA must have no gateway migration.');
$users = json_decode(file_get_contents($directory.'/accounts.json'), true);
$qr = 'water/qr/synthetic-treasurer-qa.png';
\Illuminate\Support\Facades\Storage::disk('local')->put($qr, file_get_contents($directory.'/proof.png'));
$account = \App\Models\WaterPaymentAccount::create(['label' => 'QA Treasurer receiving account', 'account_name' => 'Synthetic Club Treasurer', 'masked_account_number' => '***1234', 'qr_image_path' => $qr, 'instructions' => 'Synthetic QA image, not a payment QR. No money is sent.', 'created_by' => $users['treasurer']['id'], 'is_active' => true]);
$states = [];
foreach (['water', 'other'] as $category) {
    $c = \App\Models\WaterCollection::create(['category' => $category, 'title' => $category === 'water' ? 'September Water Contribution' : 'Faculty Club Activity Contribution', 'period' => now()->format('Y-m'), 'amount' => '500.00', 'due_date' => now()->addWeek(), 'status' => 'published', 'published_at' => now(), 'payment_account_id' => $account->id, 'created_by' => $users['treasurer']['id']]);
    $m = $c->members()->create(['user_id' => $users['faculty']['id'], 'assigned_amount' => '500.00', 'assigned_at' => now(), 'status' => 'unpaid']);
    $states[$category] = ['checkout' => route('water.checkout', $m), 'collection' => route('water.show', $c)];
}
file_put_contents($directory.'/states.json', json_encode($states));
echo "Synthetic Treasurer QR fixtures ready without gateway tables or keys.\n";
