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
$directory = $root.'/storage/app/private/targeted-qa';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
if (file_exists($directory.'/database.sqlite')) {
    fwrite(STDERR, "An isolated QA database already exists; refusing to replace it.\n");
    exit(1);
}
file_put_contents($directory.'/environment.json', json_encode(['key' => 'base64:'.base64_encode(random_bytes(32))]));
touch($directory.'/database.sqlite');
require __DIR__.'/targeted-browser-environment.php';
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
