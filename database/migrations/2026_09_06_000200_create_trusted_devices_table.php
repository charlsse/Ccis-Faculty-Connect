<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('device_name');
            $table->text('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('first_trusted_at');
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->index();
            $table->dateTime('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
