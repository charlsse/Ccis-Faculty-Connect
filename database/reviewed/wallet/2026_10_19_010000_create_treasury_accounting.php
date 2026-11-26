<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Explicit opt-in, outside the default migration path. No balances or accounts are seeded.
return new class extends Migration
{
    private function guard(): void
    {
        $c = DB::connection();
        if (app()->environment('testing') && $c->getDriverName() === 'sqlite' && $c->getDatabaseName() === ':memory:') {
            return;
        }
        $db = $c->getDatabaseName();
        if ($c->getDriverName() !== 'mysql' || $c->getConfig('host') !== '127.0.0.1' || (int) $c->getConfig('port') !== 3307
            || getenv('FC_WALLET_SCHEMA_APPROVED_DB') !== $db
            || ! ($db === 'ccis_local_20261018_164245' || preg_match('/^ccis_unified_review_[0-9_]+$/D', $db))) {
            throw new RuntimeException('Explicit reviewed-schema target approval required.');
        }
        $i = DB::selectOne('SELECT DATABASE() db, @@port port, @@innodb_force_recovery recovery');
        if ($i->db !== $db || (int) $i->port !== 3307 || (int) $i->recovery !== 0) {
            throw new RuntimeException('Schema identity rejected.');
        }
    }

    public function up(): void
    {
        $this->guard();
        foreach (['wallet_accounts', 'wallet_targets', 'wallet_destinations', 'wallet_settlements', 'wallet_withdrawals', 'wallet_entries', 'wallet_audits', 'wallet_provider_events', 'wallet_balance_observations'] as $table) {
            if (Schema::hasTable($table)) {
                throw new RuntimeException('Existing wallet schema detected; refuse partial/experimental merge.');
            }
        }
        Schema::create('wallet_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 30);
            $t->string('currency', 3);
            $t->string('merchant_id')->nullable();
            $t->string('status', 30)->default('unverified');
            $t->timestamps();
            $t->unique(['provider', 'currency', 'merchant_id']);
        });
        Schema::create('wallet_targets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('collection_id')->unique()->constrained('water_collections')->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('wallet_destinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->uuid('version')->unique();
            $t->string('label');
            $t->string('masked_number');
            $t->text('encrypted_provider_reference')->nullable();
            $t->string('status', 30)->default('unverified');
            $t->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('wallet_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->foreignId('collection_id')->constrained('water_collections')->restrictOnDelete();
            $t->foreignId('receipt_id')->nullable()->constrained('water_receipts')->restrictOnDelete();
            $t->string('provider_reference');
            $t->unsignedBigInteger('gross_minor');
            $t->unsignedBigInteger('fee_minor');
            $t->unsignedBigInteger('net_minor');
            $t->string('status', 30)->default('pending');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'provider_reference']);
        });
        Schema::create('wallet_withdrawals', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->uuid('idempotency_key')->unique();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->foreignId('collection_id')->constrained('water_collections')->restrictOnDelete();
            $t->foreignId('destination_id')->constrained('wallet_destinations')->restrictOnDelete();
            $t->uuid('destination_version');
            $t->text('destination_snapshot');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->unsignedBigInteger('fee_minor');
            $t->string('currency', 3);
            $t->string('status', 30)->index();
            $t->string('provider_id')->nullable();
            $t->string('issue_code')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'provider_id']);
        });
        Schema::create('wallet_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->foreignId('collection_id')->constrained('water_collections')->restrictOnDelete();
            $t->foreignId('settlement_id')->nullable()->constrained('wallet_settlements')->restrictOnDelete();
            $t->foreignId('withdrawal_id')->nullable()->constrained('wallet_withdrawals')->restrictOnDelete();
            $t->string('event_key')->unique();
            $t->string('kind', 50);
            foreach (['available', 'pending', 'reserved', 'withdrawn', 'fees', 'adjustments'] as $b) {
                $t->bigInteger($b)->default(0);
            }$t->timestamp('created_at');
            $t->index(['account_id', 'collection_id']);
        });
        Schema::create('wallet_audits', function (Blueprint $t) {
            $t->id();
            $t->uuid('operation_key')->unique();
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('collection_id')->nullable()->constrained('water_collections')->restrictOnDelete();
            $t->string('action', 50);
            $t->json('before_state')->nullable();
            $t->json('after_state');
            $t->timestamp('created_at');
        });
        Schema::create('wallet_provider_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->string('provider_event_id');
            $t->string('type');
            $t->string('payload_sha256', 64);
            $t->text('encrypted_payload')->nullable();
            $t->string('status', 30)->default('received');
            $t->timestamp('authenticated_at');
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
            $t->unique(['account_id', 'provider_event_id']);
        });
        Schema::create('wallet_balance_observations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->string('currency', 3);
            $t->bigInteger('cash_minor');
            $t->bigInteger('holding_minor')->nullable();
            $t->timestamp('observed_at');
            $t->string('evidence_sha256', 64);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        $this->guard();
        $tables = ['wallet_balance_observations', 'wallet_provider_events', 'wallet_audits', 'wallet_entries', 'wallet_withdrawals', 'wallet_settlements', 'wallet_destinations', 'wallet_targets', 'wallet_accounts'];
        foreach ($tables as $t) {
            if (Schema::hasTable($t) && DB::table($t)->exists()) {
                throw new RuntimeException('Populated treasury data: rollback refused; preserve history and use a forward correction.');
            }
        }
        foreach ($tables as $t) {
            Schema::dropIfExists($t);
        }
    }
};
