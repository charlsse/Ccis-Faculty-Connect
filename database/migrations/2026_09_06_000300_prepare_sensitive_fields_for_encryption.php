<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('contact_number')->nullable()->change();
            $table->longText('bio')->nullable()->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payments_reference_no_unique');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->text('reference_no')->change();
            $table->longText('notes')->nullable()->change();
            $table->char('reference_no_lookup', 64)->nullable()->after('reference_no');
            $table->unique('reference_no_lookup');
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Encrypted sensitive data cannot be safely rolled back in place. Restore the verified pre-conversion database backup instead.'
        );
    }
};
