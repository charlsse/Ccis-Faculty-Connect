<?php

if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/targeted-browser-environment.php';
waterBrowserEnvironment();
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$expected = dirname(__DIR__).'/storage/app/private/targeted-qa/database.sqlite';
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $expected) {
    throw new RuntimeException('Isolated QA database required.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$accounts = json_decode(file_get_contents(dirname($expected).'/auth-accounts.json'), true);
foreach (['member', 'administrator'] as $label) {
    $user = App\Models\User::where('email', $accounts[$label]['email'])->firstOrFail();
    $user->forceFill(['created_at' => now()->subDays(7)])->save();
    app(App\Services\PortalNotifications::class)->security($user, 'Multiple unsuccessful sign-in attempts were detected for your account. If this was not you, review your account security.');
    app(App\Services\PortalNotifications::class)->security($user, 'Your account password was changed. Review your security settings if you did not make this change.');
    $collection = App\Models\WaterCollection::whereHas('members', fn ($q) => $q->where('user_id', $user->id))->first();
    if ($collection) {
        app(App\Services\WaterPaymentService::class)->notify($user, $collection, 'September water contribution is available. Assigned amount: PHP 100.00. Review your due date.');
    }
}
echo "Prepared isolated notification fixtures and migrated QA database.\n";
