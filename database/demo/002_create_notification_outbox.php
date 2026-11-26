<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Explicit separate-connection migration; never copies demo funds to the main ledger.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('xendit_demo')->create('notification_outbox', function (Blueprint $table) {
            $table->string('event_key', 64)->primary();
            $table->unsignedBigInteger('attempt_id');
            $table->text('payload');
            $table->timestamp('created_at');
            $table->timestamp('delivered_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Demo event history must be preserved; rollback requires review.');
    }
};
