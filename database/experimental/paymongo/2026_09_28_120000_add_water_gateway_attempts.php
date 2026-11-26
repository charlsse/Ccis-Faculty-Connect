<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_gateway_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('water_member_id')->constrained()->restrictOnDelete();
            // A nullable unique reservation permits history, but one unresolved attempt.
            $table->foreignId('active_member_id')->nullable()->unique()->constrained('water_members')->restrictOnDelete();
            $table->string('provider', 30)->default('paymongo');
            $table->string('environment', 16)->default('sandbox');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('PHP');
            $table->string('provider_session_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('status', 24)->default('creating')->index();
            $table->string('issue_code', 60)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('water_submission_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('water_receipts', function (Blueprint $table) {
            // Provider verification must not impersonate a human officer.
            $table->unsignedBigInteger('verified_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Never discard gateway history or turn provider receipts into human receipts.
        if (\Illuminate\Support\Facades\DB::table('water_gateway_attempts')->exists()) {
            throw new RuntimeException('Gateway history exists. Restore a verified backup instead of rolling back financial records.');
        }
        Schema::dropIfExists('water_gateway_attempts');
        Schema::table('water_receipts', fn (Blueprint $table) => $table->unsignedBigInteger('verified_by')->nullable(false)->change());
    }
};
