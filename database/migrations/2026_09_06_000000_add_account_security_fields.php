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
            $table->string('contact_number')->nullable()->change();
            $table->text('bio')->nullable()->after('contact_number');
            $table->string('pending_email')->nullable()->after('email_verified_at');
            $table->string('pending_email_token_hash', 64)->nullable()->unique()->after('pending_email');
            $table->timestamp('pending_email_expires_at')->nullable()->after('pending_email_token_hash');
            $table->string('invitation_token_hash', 64)->nullable()->unique()->after('status');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token_hash');
            $table->timestamp('activated_at')->nullable()->after('invitation_expires_at');
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_used_step')->nullable()->after('two_factor_confirmed_at');
            $table->timestamp('last_login_at')->nullable()->after('two_factor_last_used_step');
            $table->unsignedInteger('security_version')->default(0)->after('last_login_at');
        });

        DB::table('users')->where('status', 'inactive')->update(['status' => 'deactivated']);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('contact_number')->update(['contact_number' => '']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('contact_number')->nullable(false)->change();
            $table->dropUnique(['pending_email_token_hash']);
            $table->dropUnique(['invitation_token_hash']);
            $table->dropColumn([
                'bio',
                'pending_email',
                'pending_email_token_hash',
                'pending_email_expires_at',
                'invitation_token_hash',
                'invitation_expires_at',
                'activated_at',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'two_factor_last_used_step',
                'last_login_at',
                'security_version',
            ]);
        });
    }
};
