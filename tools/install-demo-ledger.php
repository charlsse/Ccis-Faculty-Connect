<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/demo-identity.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$target = storage_path('app/private/xendit-demo.sqlite');
if (is_file($target)) {
    if (!Schema::connection('xendit_demo')->hasTable('demo_attempts')) throw new RuntimeException('Existing ledger requires review.');
    echo "Existing demo ledger retained.\n";
    exit;
}
$directory = storage_path('app/private/demo-preparation-'.date('Ymd-His'));
mkdir($directory, 0700, true);
$copy = $directory.'/disposable.sqlite';
touch($copy);
config(['database.connections.xendit_demo.database' => $copy]);
DB::purge('xendit_demo');
$db = DB::connection('xendit_demo');
// Back up and restore-test the empty demo database before its additive schema install.
$backup = $directory.'/before.sqlite';
$db->statement('VACUUM INTO '.$db->getPdo()->quote($backup));
$restore = new PDO('sqlite:'.$backup);
if ($restore->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Backup restore failed');
(require database_path('demo/001_create_demo_ledger.php'))->up();
if (!$db->getSchemaBuilder()->hasTable('demo_attempts') || $db->selectOne('PRAGMA integrity_check')->integrity_check !== 'ok') throw new RuntimeException('Schema validation failed');
$db->statement('VACUUM INTO '.$db->getPdo()->quote($target));
DB::purge('xendit_demo');
config(['database.connections.xendit_demo.database' => $target]);
if (!Schema::connection('xendit_demo')->hasTable('demo_attempts')) throw new RuntimeException('Installed ledger invalid');
file_put_contents($directory.'/verification.json', json_encode(['main_schema_changed' => false, 'disposable_migration' => 'passed', 'backup_restore' => 'passed', 'ledger' => $target], JSON_PRETTY_PRINT));
echo "Isolated ledger installed; disposable migration and backup restore passed. Main schema untouched.\n";
