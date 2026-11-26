<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reviewed additive change: retains every existing notice and financial record.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_notices', function (Blueprint $table) {
            $table->string('event_key', 64)->nullable()->unique();
            $table->text('context')->nullable();
            $table->string('email_state', 16)->default('disabled')->index();
            $table->unsignedInteger('email_attempts')->default(0);
            $table->timestamp('email_next_at')->nullable();
            $table->timestamp('email_delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Notification delivery history must be preserved; rollback requires review.');
    }
};
