<?php

namespace App\Services\Wallet;

use Illuminate\Support\Facades\DB;

final class SimulationBoundary
{
    public static function check(): void
    {
        abort_unless(config('wallet.enabled') === true, 404);
        $c = DB::connection();
        if (app()->environment('testing') && $c->getDriverName() === 'sqlite' && $c->getDatabaseName() === ':memory:') {
            return;
        }
        abort_unless($c->getDriverName() === 'mysql' && preg_match('/^ccis_wallet_test_[0-9_]+$/D', $c->getDatabaseName()) && $c->getConfig('host') === '127.0.0.1' && (int) $c->getConfig('port') === 3307, 404);
        $i = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
        abort_unless($i->db === $c->getDatabaseName() && (int) $i->port === 3307 && (int) $i->recovery === 0, 404);
    }
}
