<?php
// Explicit separate-connection installer only. Never part of main migrate.
return new class extends \Illuminate\Database\Migrations\Migration {
    public function up(): void
    {
        \Illuminate\Support\Facades\Schema::connection('xendit_demo')->create('demo_attempts', function ($t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->string('source_database');
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('water_member_id');
            $t->unsignedBigInteger('active_member_id')->nullable()->unique();
            $t->string('contribution_title');
            $t->string('billing_period');
            $t->unsignedBigInteger('amount_minor');
            $t->string('currency')->default('PHP');
            $t->string('provider')->default('xendit');
            $t->string('environment')->default('sandbox');
            $t->string('status');
            $t->string('provider_session_id')->nullable()->unique();
            $t->string('provider_payment_id')->nullable()->unique();
            $t->text('checkout_url')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('issue_code')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamps();
        });
    }
};
