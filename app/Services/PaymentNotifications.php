<?php

namespace App\Services;

use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterMember;
use App\Models\WaterNotice;
use App\Models\WaterReceipt;
use App\Models\XenditDemoAttempt;
use App\Notifications\WaterPaymentUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Main notices are a transactional email outbox; demo events commit on their own ledger first. */
class PaymentNotifications
{
    public function notice(User $user, WaterCollection $collection, string $message, string $key, array $context = []): void
    {
        if (! $user->isActive()) {
            return;
        }
        if (! Schema::hasColumn('water_notices', 'event_key')) {
            // Compatibility while the explicitly reviewed schema awaits a privileged deployment.
            WaterNotice::firstOrCreate(['user_id' => $user->id, 'water_collection_id' => $collection->id, 'message' => $message]);

            return;
        }
        WaterNotice::firstOrCreate(['event_key' => hash('sha256', $key.':'.$user->id)], [
            'user_id' => $user->id, 'water_collection_id' => $collection->id, 'message' => $message,
            'context' => $context, 'email_state' => config('water.email_enabled') ? 'pending' : 'disabled',
            'email_next_at' => now(),
        ]);
    }

    public function demoEvent(XenditDemoAttempt $attempt): void
    {
        if (! in_array($attempt->status, ['confirmed', 'expired', 'failed'], true)) {
            return;
        }
        if (! Schema::connection('xendit_demo')->hasTable('notification_outbox')) {
            return;
        }
        $key = hash('sha256', 'demo:'.$attempt->reference.':'.$attempt->status);
        // No main-database writes until this independent ledger commits.
        DB::connection('xendit_demo')->table('notification_outbox')->insertOrIgnore([
            'event_key' => $key, 'attempt_id' => $attempt->id,
            'payload' => json_encode(['status' => $attempt->status, 'user_id' => $attempt->user_id,
                'member_id' => $attempt->water_member_id, 'source_database' => $attempt->source_database]),
            'created_at' => now(),
        ]);
    }

    public function deliverDemo(): int
    {
        $sent = 0;
        if (! Schema::hasColumn('water_notices', 'event_key')) {
            return 0;
        }
        if (DB::connection('xendit_demo')->transactionLevel() > 0) {
            return 0;
        }
        if (app()->environment('testing') && config('database.connections.xendit_demo.database') !== ':memory:') {
            return 0;
        }
        if (! Schema::connection('xendit_demo')->hasTable('notification_outbox')) {
            return 0;
        }
        foreach (DB::connection('xendit_demo')->table('notification_outbox')->whereNull('delivered_at')->orderBy('created_at')->limit(100)->get() as $event) {
            try {
                $payload = json_decode($event->payload, true, 16, JSON_THROW_ON_ERROR);
                if ($payload['source_database'] !== config('xendit_demo.main_database')) {
                    throw new \RuntimeException('Source mismatch');
                }
                $attempt = XenditDemoAttempt::findOrFail($event->attempt_id);
                $member = WaterMember::with('collection', 'user')->findOrFail($payload['member_id']);
                if ($member->user_id !== $payload['user_id'] || $attempt->user_id !== $member->user_id) {
                    throw new \RuntimeException('Owner mismatch');
                }
                $confirmed = $payload['status'] === 'confirmed';
                $context = ['title' => $confirmed ? 'Test Payment Confirmed' : 'Test Payment '.ucfirst($payload['status']),
                    'route' => 'water.demo.show', 'parameters' => [$attempt->reference], 'simulation' => true, 'event' => $payload['status']];
                $message = $confirmed ? 'Your simulated online payment has been verified. View your test transaction and receipt. No funds collected; actual dues remain unchanged.'
                    : 'Your simulated checkout '.$payload['status'].'. View the transaction to review your payment options. No funds collected.';
                DB::transaction(function () use ($member, $message, $event, $context, $confirmed) {
                    $this->notice($member->user, $member->collection, $message, $event->event_key, $context);
                    if ($confirmed) {
                        foreach ($this->treasurers() as $treasurer) {
                            $this->notice($treasurer, $member->collection, 'A verified simulated online transaction is available in Transaction History. No funds collected; collection and wallet totals are unchanged.',
                                $event->event_key, array_replace($context, ['title' => 'New Test Payment Verified', 'required_permission' => 'payments.manage']));
                        }
                    }
                });
                DB::connection('xendit_demo')->table('notification_outbox')->where('event_key', $event->event_key)->update(['delivered_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Payment notification event awaits retry', ['exception_type' => get_class($e)]);
            }
        }

        return $sent;
    }

    public function treasurers()
    {
        return User::where('status', 'active')->get()->filter(fn ($user) => $user->hasPermission('payments.manage'));
    }

    public function milestones(WaterCollection $collection): void
    {
        // Only genuine receipt amounts count. Attempts, sandbox receipts and waivers never credit this sum.
        $actual = WaterReceipt::whereHas('member', fn ($q) => $q->where('water_collection_id', $collection->id))
            ->whereIn('payment_method', ['cash', 'qr'])->get()->sum(fn ($r) => WaterPaymentService::cents($r->amount));
        $target = Schema::hasTable('wallet_targets') ? DB::table('wallet_targets')->where('collection_id', $collection->id)->value('amount_minor') : null;
        $target ??= $collection->members()->get()->sum(fn ($member) => WaterPaymentService::cents($member->assigned_amount));
        if ($target < 1 || $actual < $target) {
            return;
        }
        foreach ($this->treasurers() as $treasurer) {
            $this->notice($treasurer, $collection, 'Verified actual collection receipts have reached the collection target. This does not establish a provider-held or withdrawable balance.',
                'target:'.$collection->id.':'.$target, ['title' => 'Collection Target Reached', 'route' => 'water.report', 'parameters' => [$collection->id], 'required_permission' => 'payments.manage']);
        }
    }

    public function reminders(): void
    {
        foreach (WaterMember::with('user', 'collection')->whereIn('status', ['unpaid', 'rejected'])->whereHas('collection', fn ($q) => $q->where('status', 'published'))->cursor() as $member) {
            $days = (int) now()->startOfDay()->diffInDays($member->collection->due_date->startOfDay(), false);
            if (! in_array($days, config('water.reminder_days', [3, 0, -3]), true)) {
                continue;
            }
            $this->notice($member->user, $member->collection, $member->collection->title.': PHP '.number_format($member->assigned_amount, 2).' is due '.$member->collection->due_date->format('F j, Y').'.',
                'due:'.$member->id.':'.$member->collection->due_date->format('Y-m-d').':'.$days,
                ['title' => $days < 0 ? 'Contribution Overdue' : 'Contribution Reminder', 'route' => 'water.checkout', 'parameters' => [$member->id]]);
        }
    }

    public function deliverEmail(): int
    {
        if (DB::connection()->transactionLevel() > 0) {
            return 0;
        }
        if (! Schema::hasColumn('water_notices', 'email_state')) {
            return 0;
        }
        if (! config('water.email_enabled')) {
            return 0;
        }
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if (! in_array($transport, ['smtp', 'sendmail', 'ses', 'postmark', 'resend'], true)) {
            return 0;
        }
        $sent = 0;
        foreach (WaterNotice::whereIn('email_state', ['pending', 'sending'])->where('email_attempts', '<', 5)->where('email_next_at', '<=', now())->limit(50)->get() as $notice) {
            // Atomic lease prevents two workers from sending the same event concurrently.
            $claimed = WaterNotice::whereKey($notice->id)->where('email_next_at', '<=', now())->where('email_attempts', $notice->email_attempts)
                ->update(['email_state' => 'sending', 'email_next_at' => now()->addMinutes(5), 'email_attempts' => $notice->email_attempts + 1]);
            if (! $claimed) {
                continue;
            }
            $notice->refresh();
            try {
                $user = User::findOrFail($notice->user_id);
                $permission = $notice->context['required_permission'] ?? null;
                if (! $user->isActive() || ($permission && ! $user->hasPermission($permission))
                    || ! WaterCollection::visibleTo($user)->whereKey($notice->water_collection_id)->exists()) {
                    $notice->update(['email_state' => 'disabled']);

                    continue;
                }
                $user->notify(new WaterPaymentUpdated($notice->water_collection_id, $notice->message, $notice->context));
                $notice->update(['email_state' => 'sent', 'email_delivered_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $notice->update(['email_state' => 'pending', 'email_next_at' => now()->addMinutes(5)]);
                Log::warning('Payment email awaits retry', ['notice_id' => $notice->id, 'exception_type' => get_class($e)]);
            }
        }

        return $sent;
    }
}
