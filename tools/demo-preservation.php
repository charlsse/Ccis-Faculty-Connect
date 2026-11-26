<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/demo-identity.php';
use Illuminate\Support\Facades\DB;
$baseline = $argv[2] ?? '20261106';
if (!preg_match('/^[0-9]{8}(?:-[0-9]{6})?$/D', $baseline)) throw new RuntimeException('Invalid baseline identifier');
$path = storage_path('app/private/demo-preservation-'.$baseline.'.json');
$hashes = [];
DB::statement('SET TRANSACTION READ ONLY');
DB::beginTransaction();
try {
    foreach (DB::select('SHOW TABLES') as $entry) {
        $table = array_values((array)$entry)[0];
        if (in_array($table, ['sessions','cache','cache_locks'], true)) continue;
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) throw new RuntimeException('Unexpected table');
        $rows = array_map(fn($row) => json_encode($row), DB::select('SELECT * FROM `'.$table.'`'));
        sort($rows, SORT_STRING);
        $hashes[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', implode("\n", $rows))];
    }
} finally { DB::rollBack(); }
if (($argv[1] ?? '') === 'before') {
    if (is_file($path)) throw new RuntimeException('Baseline already exists');
    file_put_contents($path, json_encode($hashes, JSON_PRETTY_PRINT));
    echo 'Baseline captured for '.count($hashes)." non-session/cache tables.\n";
} else {
    $before = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    if ($hashes !== $before) {
        foreach (array_unique(array_merge(array_keys($before), array_keys($hashes))) as $table) {
            if (($before[$table] ?? null) !== ($hashes[$table] ?? null)) echo 'Changed table: '.$table.PHP_EOL;
        }
        throw new RuntimeException('Preservation mismatch; review privately');
    }
    echo 'PASS: all '.count($hashes)." non-session/cache tables unchanged.\n";
}
echo 'Isolated demo attempts: '.App\Models\XenditDemoAttempt::count().PHP_EOL;
echo 'Demo active: '.(config('xendit_demo.enabled') ? 'yes' : 'no').PHP_EOL;
