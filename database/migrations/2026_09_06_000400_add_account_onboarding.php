<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false);
            $table->dateTime('temporary_password_expires_at')->nullable();
            $table->dateTime('password_replaced_at')->nullable();
            $table->dateTime('invitation_sent_at')->nullable();
            $table->string('invitation_delivery_status')->nullable();
            $table->dateTime('onboarding_completed_at')->nullable();
        });
        // Shared row lock serializes bootstrap and last-administrator changes.
        Schema::create('account_management_locks', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
        });
        DB::table('account_management_locks')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['must_change_password', 'temporary_password_expires_at', 'password_replaced_at', 'invitation_sent_at', 'invitation_delivery_status', 'onboarding_completed_at']);
        });
        Schema::dropIfExists('account_management_locks');
    }
};
