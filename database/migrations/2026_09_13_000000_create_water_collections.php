<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120);
            $table->string('account_name', 160);
            $table->string('masked_account_number', 40);
            $table->string('qr_image_path');
            $table->string('payment_type', 20)->default('GCash / QR Ph');
            $table->boolean('is_active')->default(false);
            $table->text('instructions')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('water_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->foreignId('active_account_id')->nullable()->constrained('water_payment_accounts')->restrictOnDelete();
        });
        DB::table('water_settings')->insert(['id' => 1]);
        Schema::create('water_collections', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('period', 7);
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->foreignId('payment_account_id')->constrained('water_payment_accounts')->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('water_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_collection_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('unpaid')->index();
            $table->decimal('assigned_amount', 12, 2);
            $table->dateTime('assigned_at');
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('waived_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->timestamps();
            $table->unique(['water_collection_id', 'user_id']);
        });
        Schema::create('water_reference_claims', function (Blueprint $table) {
            $table->string('reference_lookup', 64)->primary();
            $table->foreignId('water_member_id')->constrained()->restrictOnDelete();
        });
        Schema::create('water_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_member_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('payment_method', 20)->default('qr');
            $table->text('reference_number')->nullable();
            $table->string('reference_lookup', 64)->nullable()->index();
            $table->foreign('reference_lookup')->references('reference_lookup')->on('water_reference_claims')->restrictOnDelete();
            $table->decimal('submitted_amount', 12, 2);
            $table->dateTime('paid_at');
            $table->string('proof_path')->nullable();
            $table->text('faculty_note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->text('internal_verification_note')->nullable();
            $table->dateTime('submitted_at');
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('previous_submission_id')->nullable()->constrained('water_submissions')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['water_member_id', 'attempt_number']);
        });
        Schema::create('water_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_member_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('water_submission_id')->unique()->constrained()->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 20);
            $table->text('payment_reference')->nullable();
            $table->dateTime('payment_date');
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('verified_at');
            $table->dateTime('generated_at');
            $table->text('snapshot');
            $table->timestamps();
        });
        Schema::create('water_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('water_collection_id')->constrained()->restrictOnDelete();
            $table->string('message');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['water_notices', 'water_receipts', 'water_submissions', 'water_reference_claims', 'water_members', 'water_collections', 'water_settings', 'water_payment_accounts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
