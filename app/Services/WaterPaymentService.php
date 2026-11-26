<?php

namespace App\Services;

use App\Models\User;
use App\Models\WaterCollection;
use App\Models\WaterMember;
use App\Models\WaterNotice;
use App\Models\WaterReceipt;
use App\Models\WaterSubmission;
use App\Notifications\WaterPaymentUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WaterPaymentService
{
    public static function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public static function normalize(string $reference): string
    {
        return strtoupper(trim($reference));
    }

    public static function lookup(string $reference): string
    {
        return app(DataProtectionService::class)->paymentReferenceLookup(self::normalize($reference));
    }

    public function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['payment' => $message]);
        }
    }

    public function audit(string $action, WaterMember|WaterCollection $target, array $details = []): void
    {
        app(AuditLogger::class)->record('water.'.$action,
            $target instanceof WaterMember ? $target->user : null,
            details: ['target_type' => class_basename($target), 'target_id' => $target->id] + $details);
    }

    public function notify(User $user, WaterCollection $collection, string $message, ?string $key = null, array $context = []): void
    {
        app(PaymentNotifications::class)->notice($user, $collection, $message,
            $key ?? 'collection:'.$collection->id.':'.hash('sha256', $message), $context);
        if (config('water.email_enabled') && !in_array(config('mail.mailers.'.config('mail.default').'.transport'), ['smtp', 'sendmail', 'ses', 'postmark', 'resend'], true)) {
            app(AuditLogger::class)->record('water.email_failed', $user, details: ['collection_id' => $collection->id, 'reason' => 'unsupported_transport']);
        }
    }
    public function submit(WaterMember $member, array $data): void
    {
        Gate::authorize('submit', $member);
        DB::transaction(function () use ($member, $data) {
            $collection = WaterCollection::whereKey($member->water_collection_id)->lockForUpdate()->firstOrFail();
            $member = WaterMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            \App\Services\Payments\WaterGatewayService::requireManualAvailable($member);
            $this->require($collection->status === 'published', 'This collection is not accepting submissions.');
            $this->require(in_array($member->status, ['unpaid', 'rejected'], true), 'A pending, paid or waived obligation cannot receive another submission.');
            $this->require(self::cents($data['submitted_amount']) === self::cents($member->assigned_amount), 'The submitted amount must match the assigned amount.');
            $lookup = self::lookup($data['reference_number']);
            DB::table('water_reference_claims')->insertOrIgnore(['reference_lookup' => $lookup, 'water_member_id' => $member->id]);
            $claim = DB::table('water_reference_claims')->where('reference_lookup', $lookup)->lockForUpdate()->first();
            $this->require($claim && (int) $claim->water_member_id === $member->id, 'This reference number is already assigned to another obligation.');
            $previous = $member->submissions()->first();
            $submission = WaterSubmission::create([
                'water_member_id' => $member->id, 'attempt_number' => ($previous?->attempt_number ?? 0) + 1,
                'previous_submission_id' => $previous?->id, 'reference_number' => self::normalize($data['reference_number']),
                'reference_lookup' => $lookup, 'submitted_amount' => $data['submitted_amount'], 'paid_at' => $data['paid_at'],
                'proof_path' => $data['proof_path'], 'faculty_note' => $data['faculty_note'] ?? null,
                'status' => 'pending', 'submitted_at' => now(), 'payment_method' => 'qr',
            ]);
            $member->update(['status' => 'pending']);
            $this->audit('submitted', $member, ['submission_id' => $submission->id, 'attempt' => $submission->attempt_number, 'from' => $previous ? 'rejected' : 'unpaid', 'to' => 'pending']);
            User::where('status', 'active')->get()->filter(fn ($u) => $u->hasPermission('payments.manage'))->each(
                fn ($u) => $this->notify($u, $collection, $previous ? 'A corrected collection payment awaits manual verification.' : 'A collection payment awaits manual verification.',
                    'submission:'.$submission->id.':pending', ['title' => 'Payment Awaiting Review', 'route' => 'water.submission', 'parameters' => [$submission->id], 'required_permission' => 'payments.manage'])
            );
            $this->notify($member->user, $collection, 'Your payment proof has been received and is awaiting Treasurer verification.',
                'submission:'.$submission->id.':pending', ['title' => 'Payment Submitted', 'route' => 'water.submission', 'parameters' => [$submission->id]]);
        }, 3);
    }

    public function review(WaterSubmission $submission, array $data): void
    {
        Gate::authorize('manage', WaterCollection::class);
        DB::transaction(function () use ($submission, $data) {
            $member = WaterMember::whereKey($submission->water_member_id)->lockForUpdate()->firstOrFail();
            \App\Services\Payments\WaterGatewayService::requireManualAvailable($member);
            $submission = WaterSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            $this->require($member->status === 'pending' && $submission->status === 'pending', 'This submission has already been reviewed.');
            $this->require(! $member->receipt()->exists(), 'This obligation already has a receipt.');
            $approve = $data['decision'] === 'approve';
            if ($approve) {
                $this->require(self::cents($submission->submitted_amount) === self::cents($member->assigned_amount), 'The amount does not match the obligation.');
                $claim = DB::table('water_reference_claims')->where('reference_lookup', $submission->reference_lookup)->lockForUpdate()->first();
                $this->require($claim && (int) $claim->water_member_id === $member->id && ! WaterSubmission::where('reference_lookup', $submission->reference_lookup)->where('water_member_id', '!=', $member->id)->exists(), 'The reference is missing or duplicated.');
            } else {
                $this->require(trim($data['rejection_reason'] ?? '') !== '', 'A rejection or correction reason is required.');
            }
            $submission->update(['status' => $approve ? 'approved' : 'rejected', 'verified_by' => auth()->id(), 'verified_at' => now(),
                'internal_verification_note' => $data['internal_verification_note'] ?? null,
                'rejection_reason' => $approve ? null : $data['rejection_reason']]);
            $member->update(['status' => $approve ? 'paid' : 'rejected', 'paid_at' => $approve ? $submission->paid_at : null]);
            if ($approve) {
                $this->receipt($member, $submission);
                app(PaymentNotifications::class)->milestones($member->collection);
            }
            $this->audit($data['decision'], $member, ['submission_id' => $submission->id, 'from' => 'pending', 'to' => $member->status]);
            $message = $approve ? 'Your collection payment was verified. Your receipt is ready.' : 'A correction or rejection requires your attention: '.trim($data['rejection_reason']).' Review the reason and resubmit your proof.';
            $this->notify($member->user, $member->collection, $message, 'submission:'.$submission->id.':'.$submission->status,
                ['title' => $approve ? 'Payment Approved' : ($data['decision'] === 'correct' ? 'Correction Requested' : 'Payment Rejected'), 'route' => 'water.submission', 'parameters' => [$submission->id]]);
        }, 3);
    }

    public function cash(WaterMember $member, array $data): void
    {
        Gate::authorize('manage', WaterCollection::class);
        DB::transaction(function () use ($member, $data) {
            $collection = WaterCollection::whereKey($member->water_collection_id)->lockForUpdate()->firstOrFail();
            $member = WaterMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->require(in_array($collection->status, ['published', 'closed'], true), 'Cash may be recorded only for published or closed collections.');
            \App\Services\Payments\WaterGatewayService::requireManualAvailable($member);
            $this->require(in_array($member->status, ['unpaid', 'rejected'], true), 'Resolve any pending submission first. Paid or waived obligations cannot receive cash.');
            $this->require(self::cents($data['submitted_amount']) === self::cents($member->assigned_amount), 'Cash received must match the assigned amount.');
            $previous = $member->submissions()->first();
            $submission = WaterSubmission::create(['water_member_id' => $member->id, 'attempt_number' => ($previous?->attempt_number ?? 0) + 1,
                'previous_submission_id' => $previous?->id, 'payment_method' => 'cash', 'submitted_amount' => $data['submitted_amount'],
                'paid_at' => $data['paid_at'], 'status' => 'approved', 'submitted_at' => now(), 'verified_at' => now(), 'verified_by' => auth()->id(),
                'internal_verification_note' => $data['internal_verification_note'] ?? null]);
            $member->update(['status' => 'paid', 'paid_at' => $submission->paid_at]);
            $this->receipt($member, $submission);
            app(PaymentNotifications::class)->milestones($collection);
            $this->audit('cash_recorded', $member, ['submission_id' => $submission->id, 'to' => 'paid']);
            $this->notify($member->user, $collection, 'Your cash collection payment was recorded. Your verified receipt is ready.');
        }, 3);
    }

    public function receipt(WaterMember $member, WaterSubmission $submission): WaterReceipt
    {
        $automatic = in_array($submission->payment_method, ['paymongo_test', 'xendit_test'], true);
        // Submission IDs are allocated by the database; uniqueness does not depend on a count or timestamp.
        return WaterReceipt::create(['water_member_id' => $member->id, 'water_submission_id' => $submission->id,
            'receipt_number' => ($member->collection->category === 'other' ? 'FC-CLUB-' : 'FC-WATER-').now()->format('Ym').'-'.str_pad((string) $submission->id, 6, '0', STR_PAD_LEFT),
            'amount' => $submission->submitted_amount, 'payment_method' => $submission->payment_method,
            'payment_reference' => $submission->reference_number, 'payment_date' => $submission->paid_at,
            'verified_by' => $automatic ? null : auth()->id(), 'verified_at' => $submission->verified_at, 'generated_at' => now(),
            'snapshot' => ['faculty_name' => $member->user->full_name, 'collection_title' => $member->collection->title,
                'period' => $member->collection->period, 'officer_name' => $automatic ? 'Automatic - '.($submission->payment_method === 'xendit_test' ? 'Xendit' : 'PayMongo').' TEST' : auth()->user()->full_name]]);
    }

    public function waive(WaterMember $member, string $reason): void
    {
        Gate::authorize('manage', WaterCollection::class);
        DB::transaction(function () use ($member, $reason) {
            $collection = WaterCollection::whereKey($member->water_collection_id)->lockForUpdate()->firstOrFail();
            $member = WaterMember::whereKey($member->id)->lockForUpdate()->firstOrFail();
            $this->require(in_array($collection->status, ['published', 'closed'], true) && in_array($member->status, ['unpaid', 'rejected'], true), 'Only unpaid or rejected published/closed obligations can be waived.');
            \App\Services\Payments\WaterGatewayService::requireManualAvailable($member);
            $this->require(trim($reason) !== '', 'A waiver reason is required.');
            $member->update(['status' => 'waived', 'waived_at' => now(), 'waived_by' => auth()->id(), 'waiver_reason' => $reason]);
            $this->audit('waived', $member, ['to' => 'waived']);
            $this->notify($member->user, $collection, 'Your collection obligation was waived by an authorized officer.');
        });
    }
}
