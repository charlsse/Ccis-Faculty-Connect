<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('water_collections', fn (Blueprint $table) => $table->string('category', 16)->default('water')->index());
    }

    public function down(): void
    {
        Schema::table('water_collections', fn (Blueprint $table) => $table->dropColumn('category'));
    }
};
