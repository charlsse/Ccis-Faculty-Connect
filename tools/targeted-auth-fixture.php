<?php

if (PHP_SAPI !== 'cli') { exit; }
require __DIR__.'/targeted-browser-environment.php';
waterBrowserEnvironment();
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = dirname(__DIR__).'/storage/app/private/targeted-qa';
if (config('database.connections.sqlite.database') !== $directory.'/database.sqlite') { throw new RuntimeException('QA guard'); }
$accounts = [];
$run = bin2hex(random_bytes(4));
foreach (['member', 'administrator', 'secretary', 'temporary', 'setup', 'reset'] as $label) {
    $password = bin2hex(random_bytes(16));
    $user = App\Models\User::factory()->create(['faculty_id' => 'QA-'.$label.'-'.$run, 'email' => $label.$run.'@targeted.example', 'first_name' => 'Synthetic', 'last_name' => ucfirst($label),
        'role' => match ($label) { 'administrator' => 'admin', 'secretary' => 'secretary', default => 'faculty' }, 'password' => $password]);
    $codes = app(App\Services\TwoFactorService::class)->generateRecoveryCodes($user);
    if ($label === 'temporary') {
        $user->forceFill(['status' => 'pending_activation', 'must_change_password' => true, 'temporary_password_expires_at' => now()->addDay(),
            'password_replaced_at' => null, 'activated_at' => null, 'email_verified_at' => null, 'two_factor_confirmed_at' => null])->save();
    }
    if ($label === 'setup') { $user->forceFill(['two_factor_confirmed_at' => null, 'two_factor_secret' => null])->save(); }
    $accounts[$label] = ['email' => $user->email, 'password' => $password, 'recovery' => $codes[0]];
    if ($label === 'reset') { $accounts[$label]['token'] = Illuminate\Support\Facades\Password::createToken($user); }
    if ($label === 'member') {
        $account = App\Models\WaterPaymentAccount::firstOrFail();
        $collection = App\Models\WaterCollection::create(['title' => 'Synthetic Personal Water', 'period' => '2026-09', 'amount' => '100.00',
            'due_date' => now()->addWeek(), 'payment_account_id' => $account->id, 'status' => 'published', 'published_at' => now(), 'created_by' => $account->created_by]);
        $collection->members()->create(['user_id' => $user->id, 'assigned_amount' => '100.00', 'assigned_at' => now(), 'status' => 'unpaid']);
    }
}
file_put_contents($directory.'/auth-accounts.json', json_encode($accounts));
file_put_contents($directory.'/invitation-preview.html', view('mail.invitation', ['name' => 'Synthetic Member', 'role' => 'Faculty Member',
    'username' => 'dummy@example.test', 'temporaryPassword' => 'Dummy-Preview-Only!123', 'expiresAt' => 'September 24, 2026 1:00 AM UTC',
    'loginUrl' => config('app.url').'/login', 'support' => null])->render());
echo "Synthetic auth fixtures and dummy email preview prepared. No email sent.\n";
