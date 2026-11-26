<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('payments:maintain {--no-provider : Skip provider status reads} {--no-email : Never send email}', function () {
    if (app()->environment('testing') && config('database.connections.xendit_demo.database') !== ':memory:') {
        $this->warn('Tests must not access the installed demo ledger.');
        return 0;
    }
    if (!\Illuminate\Support\Facades\Schema::hasColumn('water_notices', 'event_key')
        || !\Illuminate\Support\Facades\Schema::connection('xendit_demo')->hasTable('notification_outbox')) {
        $this->warn('Payment notification maintenance awaits the reviewed additive schema deployment. No records changed.');
        return 0;
    }
    $service = app(\App\Services\PaymentNotifications::class);
    if (!$this->option('no-provider')) {
        $demo = app(\App\Services\Payments\XenditDemoService::class);
        if ($demo->ready()) {
            \App\Models\XenditDemoAttempt::whereNotNull('provider_session_id')->whereIn('status', ['creating', 'unknown', 'pending'])
                ->orderBy('last_checked_at')->limit(20)->each(function ($attempt) use ($demo) {
                    try { $demo->check($attempt); } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('Payment reconciliation deferred', ['exception_type' => get_class($e)]); }
                });
        }
    }
    // Recovery also covers terminal events verified before the outbox was installed.
    if ((!app()->environment('testing') || config('database.connections.xendit_demo.database') === ':memory:')
        && \Illuminate\Support\Facades\Schema::connection('xendit_demo')->hasTable('notification_outbox')) {
        \App\Models\XenditDemoAttempt::whereIn('status', ['confirmed', 'expired', 'failed'])->orderBy('id')->each(fn ($attempt) => $service->demoEvent($attempt));
    }
    $delivered = $service->deliverDemo();
    $service->reminders();
    \App\Models\WaterCollection::whereIn('status', ['published', 'closed'])->each(fn ($collection) => $service->milestones($collection));
    $emails = $this->option('no-email') ? 0 : $service->deliverEmail();
    $this->info('Notification events delivered: '.$delivered.'. Emails accepted by transport: '.$emails.'. Existing sessions only; no checkout created.');
})->purpose('Recover payment notifications, reconcile existing demo sessions, and deliver configured reminders/email');

\Illuminate\Support\Facades\Schedule::command('payments:maintain')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('water:gateway-reconcile {--session= : PayMongo checkout ID for recovering an unknown creation response}', function () {
    $service = app(\App\Services\Payments\WaterGatewayService::class);
    if (! $service->available()) { $this->error('PayMongo sandbox configuration or migration is incomplete.'); return 1; }
    if ($id = $this->option('session')) {
        try { $attempt = $service->reconcile($id); $this->info($attempt ? 'Reconciled local attempt: '.$attempt->status : 'No matching local attempt.'); }
        catch (\App\Services\Payments\GatewayUnavailable $e) { $this->error('Provider verification unavailable; state preserved.'); return 1; }
        return 0;
    }
    \App\Models\WaterGatewayAttempt::whereNotNull('active_member_id')->whereNotNull('provider_session_id')->orderBy('id')->limit(100)->get()
        ->each(fn ($attempt) => $service->check($attempt));
    $this->info('Checked up to 100 unresolved attempts. Unknown outcomes remain reserved.');
})->purpose('Reconcile existing PayMongo TEST attempts without creating new charges');
