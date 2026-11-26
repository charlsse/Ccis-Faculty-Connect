<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Opt-in only. Both adapters share the existing gateway schema; apply only ONE setup path.
return new class extends Migration
{
    private function shared(): Migration
    {
        return require dirname(__DIR__).'/paymongo/2026_09_28_120000_add_water_gateway_attempts.php';
    }

    public function up(): void
    {
        if (Schema::hasTable('water_gateway_attempts')) {
            throw new RuntimeException('Gateway schema already exists. Do not apply both gateway setup migrations.');
        }
        $this->shared()->up();
    }

    public function down(): void
    {
        if (DB::table('water_gateway_attempts')->exists() || DB::table('water_receipts')->whereNull('verified_by')->exists()) {
            throw new RuntimeException('Preserve gateway history and automatic receipts; rollback refused.');
        }
        $this->shared()->down();
    }
};
