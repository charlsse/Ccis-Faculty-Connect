<?php

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$root = dirname(__DIR__);
$directory = $root.'/storage/app/private/workspace-glass-qa';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
if (file_exists($directory.'/database.sqlite')) {
    fwrite(STDERR, "An isolated QA database already exists; refusing to replace it.\n");
    exit(1);
}
file_put_contents($directory.'/environment.json', json_encode(['key' => 'base64:'.base64_encode(random_bytes(32))]));
touch($directory.'/database.sqlite');
require __DIR__.'/workspace-glass-environment.php';
waterBrowserEnvironment();
require $root.'/packages/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $directory.'/database.sqlite') {
    throw new RuntimeException('Isolated database guard failed.');
}
Artisan::call('migrate', ['--force' => true]);
$accounts = [];
foreach (['treasurer', 'faculty', 'cash', 'waived', 'admin'] as $label) {
    $password = bin2hex(random_bytes(16));
    $user = User::factory()->create(['first_name' => 'QA '.ucfirst($label), 'middle_name' => null, 'last_name' => 'Synthetic',
        'email' => $label.'@water-test.example', 'faculty_id' => 'QA-WATER-'.strtoupper($label),
        'role' => in_array($label, ['treasurer', 'admin']) ? $label : 'faculty', 'password' => $password]);
    $recovery = app(TwoFactorService::class)->generateRecoveryCodes($user);
    $accounts[$label] = ['email' => $user->email, 'password' => $password, 'recovery' => $recovery[0], 'id' => $user->id];
}
file_put_contents($directory.'/accounts.json', json_encode($accounts));
file_put_contents($directory.'/proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
echo "Isolated SQLite migrations and synthetic QA accounts prepared. No existing application records were changed.\n";


// Rich synthetic records for visual coverage in this isolated SQLite database only.
$admin = User::findOrFail($accounts['admin']['id']);
$faculty = User::findOrFail($accounts['faculty']['id']);
$officer = User::findOrFail($accounts['treasurer']['id']);
App\Models\User::factory()->count(12)->create(['department'=>'CCIS']);
foreach (['Faculty assembly and community updates','A shared space for teaching resources','Contribution records are ready for review'] as $title) {
    App\Models\Announcement::factory()->create(['title'=>$title,'published_at'=>now()->subHour(),'created_by'=>$admin->id]);
}
for($i=1;$i<=8;$i++) App\Models\Payment::create(['user_id'=>$faculty->id,'recorded_by'=>$officer->id,'description'=>'Synthetic faculty contribution '.$i,'reference_no'=>'QA-WS-'.$i,'amount'=>150,'status'=>$i%3 ? 'paid':'pending','due_date'=>now()->addDays(10),'paid_at'=>$i%3 ? now():null]);
$account = App\Models\WaterPaymentAccount::create(['label'=>'QA Faculty Club','account_name'=>'Synthetic CCIS Club','masked_account_number'=>'09** *** 1234','qr_image_path'=>'qa-not-configured.png','is_active'=>true,'created_by'=>$officer->id]);
$collection = App\Models\WaterCollection::create(['title'=>'October faculty contribution','period'=>now()->format('Y-m'),'amount'=>100,'due_date'=>now()->addDays(7),'payment_account_id'=>$account->id,'status'=>'published','published_at'=>now(),'created_by'=>$officer->id]);
foreach ([$faculty,$admin,$officer] as $member) $collection->members()->create(['user_id'=>$member->id,'assigned_amount'=>100,'assigned_at'=>now(),'status'=>'unpaid']);
App\Models\WaterCollection::create(['title'=>'Synthetic draft collection','period'=>now()->format('Y-m'),'amount'=>125,'due_date'=>now()->addDays(20),'payment_account_id'=>$account->id,'status'=>'draft','created_by'=>$officer->id]);
