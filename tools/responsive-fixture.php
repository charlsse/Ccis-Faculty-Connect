<?php

// Reuse the existing guarded, isolated SQLite setup. No live application data.
if (PHP_SAPI !== 'cli') { exit; }
require __DIR__.'/targeted-auth-fixture.php';
$accounts = json_decode(file_get_contents($directory.'/auth-accounts.json'), true);
$password = bin2hex(random_bytes(16));
$user = App\Models\User::factory()->create(['faculty_id' => 'QA-treasurer-'.$run, 'email' => 'treasurer'.$run.'@responsive.example', 'first_name' => 'Synthetic',
    'last_name' => 'Treasurer', 'role' => 'treasurer', 'password' => $password]);
$codes = app(App\Services\TwoFactorService::class)->generateRecoveryCodes($user);
$accounts['treasurer'] = ['email' => $user->email, 'password' => $password, 'recovery' => $codes[0]];
file_put_contents($directory.'/auth-accounts.json', json_encode($accounts));
echo "Synthetic Treasurer prepared for responsive verification.\n";
