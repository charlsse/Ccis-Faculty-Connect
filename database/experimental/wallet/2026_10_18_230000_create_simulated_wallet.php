<?php

use App\Services\Wallet\SimulationBoundary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        SimulationBoundary::check();
        Schema::create('wallet_accounts', function (Blueprint $t) {
            $t->id();
            $t->string('currency', 3);
            $t->string('mode');
            $t->timestamps();
        });
        Schema::create('wallet_targets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('collection_id')->unique()->constrained('water_collections')->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('wallet_destinations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->string('label');
            $t->string('masked_number');
            $t->string('version')->unique();
            $t->boolean('active');
            $t->timestamps();
        });
        Schema::create('wallet_settlements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->foreignId('collection_id')->constrained('water_collections')->restrictOnDelete();
            $t->foreignId('receipt_id')->unique()->constrained('water_receipts')->restrictOnDelete();
            $t->unsignedBigInteger('gross_minor');
            $t->unsignedBigInteger('fee_minor');
            $t->string('status')->index();
            $t->timestamps();
        });
        Schema::create('wallet_withdrawals', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->uuid('idempotency_key')->unique();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->foreignId('collection_id')->constrained('water_collections')->restrictOnDelete();
            $t->foreignId('destination_id')->constrained('wallet_destinations')->restrictOnDelete();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->string('destination_snapshot');
            $t->string('destination_version');
            $t->unsignedBigInteger('amount_minor');
            $t->unsignedBigInteger('fee_minor');
            $t->string('status')->index();
            $t->timestamps();
        });
        Schema::create('wallet_entries', function (Blueprint $t) {
            $t->id();
            $t->string('event_key')->unique();
            $t->foreignId('account_id')->constrained('wallet_accounts')->restrictOnDelete();
            $t->foreignId('collection_id')->constrained('water_collections')->restrictOnDelete();
            $t->foreignId('withdrawal_id')->nullable()->constrained('wallet_withdrawals')->restrictOnDelete();
            $t->string('kind');
            foreach (['available', 'pending', 'reserved', 'withdrawn', 'fees'] as $b) {
                $t->bigInteger($b)->default(0);
            }$t->timestamp('created_at');
            $t->index(['account_id', 'collection_id']);
        });
    }

    public function down(): void
    {
        SimulationBoundary::check();
        if (DB::table('wallet_entries')->exists() || DB::table('wallet_withdrawals')->exists()) {
            throw new RuntimeException('Wallet history exists; rollback refused. Use a forward migration.');
        }
        foreach (['wallet_entries', 'wallet_withdrawals', 'wallet_settlements', 'wallet_destinations', 'wallet_targets', 'wallet_accounts'] as $t) {
            Schema::drop($t);
        }
    }
};
