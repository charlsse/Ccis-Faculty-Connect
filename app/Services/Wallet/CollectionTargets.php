<?php

namespace App\Services\Wallet;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Normal collection policy only; never credits a balance or requests a transfer. */
final class CollectionTargets
{
    public function ready(): bool
    {
        return ! config('wallet.enabled') && Schema::hasTable('wallet_audits') && Schema::hasTable('wallet_targets') && Schema::hasColumn('wallet_targets', 'revision');
    }

    public function set(User $user, int $collection, int $amount, int $revision, string $key): void
    {
        abort_unless($user->isActive() && $user->role === 'treasurer' && $user->hasPermission('payments.manage'), 403);
        abort_unless($this->ready(), 409, 'Collection targets await approved schema deployment.');
        if ($amount < 1 || $amount > 99999999999 || ! Str::isUuid($key) || $revision < 0) {
            throw ValidationException::withMessages(['target' => 'Invalid target request.']);
        }
        DB::transaction(function () use ($user, $collection, $amount, $revision, $key) {
            abort_unless(DB::table('water_collections')->where('id', $collection)->lockForUpdate()->first(), 404);
            $prior = DB::table('wallet_audits')->where('operation_key', $key)->first();
            if ($prior) {
                $after = json_decode($prior->after_state, true);
                if ((int) $prior->actor_id !== $user->id || (int) $prior->collection_id !== $collection || ($after['amount_minor'] ?? null) !== $amount) {
                    throw ValidationException::withMessages(['target' => 'Conflicting duplicate request.']);
                }

return;
            }
            $old = DB::table('wallet_targets')->where('collection_id', $collection)->first();
            if ((int) ($old->revision ?? 0) !== $revision) {
                throw ValidationException::withMessages(['target' => 'Target changed since this page was opened. Refresh and review it again.']);
            }
            DB::table('wallet_targets')->updateOrInsert(['collection_id' => $collection], ['amount_minor' => $amount, 'revision' => $revision + 1, 'updated_by' => $user->id, 'created_at' => $old->created_at ?? now(), 'updated_at' => now()]);
            DB::table('wallet_audits')->insert(['operation_key' => $key, 'actor_id' => $user->id, 'collection_id' => $collection, 'action' => 'collection_target_updated', 'before_state' => $old ? json_encode(['amount_minor' => (int) $old->amount_minor, 'revision' => $revision]) : null, 'after_state' => json_encode(['amount_minor' => $amount, 'revision' => $revision + 1]), 'created_at' => now()]);
        }, 3);
    }
}
