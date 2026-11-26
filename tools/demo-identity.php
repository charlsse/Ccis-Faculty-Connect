<?php
require dirname(__DIR__).'/packages/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.host') !== '127.0.0.1' || (int) config('database.connections.mysql.port') !== 3307 || config('database.connections.mysql.database') !== 'ccis_local_20261018_164245') throw new RuntimeException('Unexpected configured database');
$identity = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
if ($identity->db !== 'ccis_local_20261018_164245' || (int)$identity->port !== 3307 || (int)$identity->recovery !== 0) throw new RuntimeException('Unexpected server');
echo json_encode($identity).PHP_EOL;
echo 'Migrations: '.Illuminate\Support\Facades\DB::table('migrations')->count().PHP_EOL;
echo 'Real receipts: '.App\Models\WaterReceipt::count().PHP_EOL;
