<?php

use App\Models\User;
use App\Models\WaterCollection;
use App\Services\Wallet\CollectionTargets;
use Illuminate\Validation\ValidationException;

if (PHP_SAPI !== 'cli') {
    exit;
}
try {
    require __DIR__.'/bootstrap.php';
    $u = User::where('email', 'concurrency@unified-review.invalid')->findOrFail((int) ($argv[2] ?? 0));
    $c = WaterCollection::firstOrFail();
    (new CollectionTargets)->set($u, $c->id, 10000, 0, $argv[1]);
    echo 'saved';
} catch (ValidationException $e) {
    echo 'stale';
} catch (Throwable $e) {
    file_put_contents(dirname(getenv('FC_UNIFIED_CONFIG')).'/quota-worker-error.private.txt', get_class($e).' '.$e->getMessage());
    echo 'failed';
    exit(1);
}
