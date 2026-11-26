<?php
// Local diagnostic: authenticate only; never send mail or print credentials.
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'Mailer: '.config('mail.default').PHP_EOL;
echo 'Config cached: '.($app->configurationIsCached() ? 'yes' : 'no').PHP_EOL;
foreach (\App\Models\AuditLog::where('action', 'password_reset.delivery_failed')->latest('created_at')->limit(3)->get() as $row) {
    echo 'Recent failure: '.$row->created_at.' '.($row->details['exception_type'] ?? 'unknown').PHP_EOL;
}
config(['mail.mailers.smtp.timeout' => 10]);
if (isset($argv[1])) {
    if (! in_array($argv[1], ['587', '2525', '465'], true)) exit(2);
    config(['mail.mailers.smtp.port' => (int) $argv[1], 'mail.mailers.smtp.scheme' => $argv[1] === '465' ? 'smtps' : 'smtp']);
}
echo 'Port: '.config('mail.mailers.smtp.port').PHP_EOL;
try {
    $transport = app('mail.manager')->mailer('smtp')->getSymfonyTransport();
    $transport->start();
    echo "SMTP connection, TLS and authentication successful. No email sent.\n";
    $transport->stop();
} catch (\Throwable $e) {
    $message = $e->getMessage();
    foreach (['mail.mailers.smtp.username', 'mail.mailers.smtp.password', 'mail.from.address'] as $key) {
        $value = (string) config($key);
        if ($value !== '') $message = str_replace([$value, base64_encode($value)], '[redacted]', $message);
    }
    $message = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[email]', $message);
    echo get_class($e).': '.substr($message, 0, 1300).PHP_EOL;
    exit(1);
}
