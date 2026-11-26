<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AccountInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly SessionManager $sessions) {}

    public function send(User $user, ?User $actor = null): bool
    {
        if ($user->isAcademicRosterDemo()) {
            throw ValidationException::withMessages(['invitation' => 'Academic roster credentials are issued locally by an administrator; email invitations are unavailable.']);
        }
        // Synchronous delivery under the row lock prevents stale queued invitations.
        return DB::transaction(function () use ($user, $actor): bool {
            $member = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($member->status !== User::STATUS_PENDING || $member->password_replaced_at || $member->activated_at) {
                throw ValidationException::withMessages(['invitation' => 'Use password reset for an activated account. Only pending invitations can be issued.']);
            }
            $temporary = Str::password(24);
            $expires = now()->addHours(config('account-security.temporary_password_hours', 48));
            $member->forceFill([
                'password' => Hash::make($temporary),
                'must_change_password' => true,
                'temporary_password_expires_at' => $expires,
                'invitation_token_hash' => null,
                'invitation_expires_at' => null,
                'invitation_delivery_status' => 'issuing',
                'remember_token' => null,
            ])->save();
            $this->sessions->invalidate($member);
            try {
                $transport = config('mail.mailers.'.config('mail.default').'.transport');
                if (! app()->runningUnitTests() && ! in_array($transport, ['smtp', 'sendmail', 'ses', 'postmark', 'resend'], true)) {
                    throw new \RuntimeException('Invitation mail requires an approved synchronous transport.');
                }
                $member->notifyNow(new AccountInvitation($temporary, $expires->format('F j, Y g:i A T')));
                $member->forceFill(['invitation_sent_at' => now(), 'invitation_delivery_status' => 'accepted'])->save();
                $this->auditLogger->record('invitation.sent', $member, $actor, ['transport_status' => 'accepted']);

                return true;
            } catch (Throwable $exception) {
                $member->forceFill([
                    'password' => Hash::make(Str::random(64)),
                    'temporary_password_expires_at' => null,
                    'invitation_delivery_status' => 'failed',
                ])->save();
                $this->auditLogger->record('invitation.delivery_failed', $member, $actor, ['exception_type' => $exception::class]);

                return false;
            } finally {
                unset($temporary);
            }
        });
    }
}
