<?php

// Only the existing isolated loopback QA database may be used by this fixture.
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
$count = (int) ($argv[1] ?? 3);
if (! in_array($count, [0, 1, 3], true)) throw new RuntimeException('Unsupported fixture count.');
App\Models\Announcement::query()->delete();
for ($i = 0; $i < $count; $i++) {
    App\Models\Announcement::factory()->create([
        'created_by' => App\Models\User::where('role', 'admin')->firstOrFail()->id,
        'title' => $i === 2 ? str_repeat('Long faculty community update ', 5) : ['Faculty club assembly: a shared agenda', 'Water contributions: keep your records in view'][$i],
        'body' => $i === 2 ? str_repeat('A detailed faculty update with information for every member. ', 60) : 'Find the latest club information in your faculty workspace. Open the full announcement for the details and supporting information.',
        'published_at' => now()->subMinutes($i + 1),
        'category' => $i === 1 ? 'Contributions' : 'Faculty Club',
        'priority' => $i === 0 ? 'important' : 'normal',
        'is_pinned' => false,
    ]);
}
echo "Prepared {$count} synthetic published announcements in isolated QA.\n";
