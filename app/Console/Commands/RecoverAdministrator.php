<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InvitationService;
use App\Services\SessionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class RecoverAdministrator extends Command
{
    protected $signature = 'facultyconnect:recover-admin';

    protected $description = 'Explicit operator recovery for an exactly identified administrator';

    public function handle(AuditLogger $audit, SessionManager $sessions, InvitationService $invitations): int
    {
        $email = trim((string) $this->ask('Administrator official email'));
        $identifier = trim((string) $this->ask('Administrator employee ID'));
        $user = User::where('email', $email)->where('faculty_id', $identifier)->where('role', User::ROLE_ADMIN)->first();
        if (! $user) {
            $this->error('No administrator matches both identifiers.');

            return self::FAILURE;
        }
        $action = $this->choice('Recovery action', ['password-reset', 'reset-2fa', 'resend-invitation']);
        if (! $this->confirm('Perform '.$action.' for administrator record #'.$user->id.'?')) {
            return self::FAILURE;
        }
        try {
            if ($action === 'reset-2fa') {
                DB::transaction(function () use ($user, $sessions): void {
                    $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null,
                        'two_factor_recovery_codes' => null, 'two_factor_last_used_step' => null])->save();
                    $sessions->invalidate($user);
                });
            } elseif ($action === 'resend-invitation') {
                if (! $invitations->send($user)) {
                    throw new \RuntimeException('Mail failed.');
                }
            } else {
                if ($user->status !== User::STATUS_ACTIVE || $user->must_change_password) {
                    $this->error('Password reset requires an active account with a permanent password.');

                    return self::FAILURE;
                }
                if (Password::sendResetLink(['email' => $user->email]) !== Password::RESET_LINK_SENT) {
                    throw new \RuntimeException('Mail not accepted.');
                }
            }
            $audit->record('administrator.recovery_by_operator', $user, details: ['operation' => $action]);
            $this->info($action === 'reset-2fa' ? '2FA reset; sessions and browser trust revoked. Re-enrollment required.' : 'Message accepted by the mail transport; inbox delivery is not confirmed.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $audit->record('administrator.recovery_failed', $user, details: ['exception_type' => $exception::class]);
            $this->error('Recovery could not complete. Check account status and mail configuration, then retry.');

            return self::FAILURE;
        }
    }
}
