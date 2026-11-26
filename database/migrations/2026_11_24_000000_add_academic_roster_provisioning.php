<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable()->change());
        Schema::create('academic_roster_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('roster_key', 40)->unique();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('provisioned_by')->constrained('users')->restrictOnDelete();
            $table->string('source_name');
            $table->string('employment_classification', 20);
            $table->string('source_sha256', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        // Never invent emails or remove the exception while email-less users remain.
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('Assign genuine verified emails before rolling back roster provisioning.');
        }
        Schema::dropIfExists('academic_roster_accounts');
        Schema::table('users', fn (Blueprint $table) => $table->string('email')->nullable(false)->change());
    }
};
