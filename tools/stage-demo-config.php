<?php
// CLI only. Copies only existing test credentials; never prints them or changes .env.
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/demo-identity.php';
$target = storage_path('app/private/xendit-demo-config.json');
if (is_file($target)) throw new RuntimeException('Existing configuration retained; review before replacement.');
$source = 'C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018/runtime.env';
$old = \Dotenv\Dotenv::parse(file_get_contents($source));
$credentials = [];
foreach (['secret_key' => 'XENDIT_SECRET_KEY', 'callback_token' => 'XENDIT_CALLBACK_TOKEN', 'business_id' => 'XENDIT_BUSINESS_ID'] as $key => $name) $credentials[$key] = $old[$name] ?? '';
$credentials['treasurer_account_confirmed'] = ($old['XENDIT_TREASURER_ACCOUNT_CONFIRMED'] ?? '') === 'true';
if (!str_starts_with($credentials['secret_key'], 'xnd_development_') || strlen($credentials['callback_token']) < 16 || !preg_match('/^[a-f0-9]{24}$/D', $credentials['business_id']) || !$credentials['treasurer_account_confirmed']) throw new RuntimeException('Existing test credentials incomplete.');
file_put_contents($target, json_encode(['enabled' => false, 'return_base' => 'https://skid-porridge-snoring.ngrok-free.dev', 'credentials' => $credentials], JSON_PRETTY_PRINT), LOCK_EX);
echo "Existing test credentials staged privately. Demonstration activation remains OFF. No tunnel started.\n";
