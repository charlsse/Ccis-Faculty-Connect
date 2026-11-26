<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    private const FORBIDDEN_DETAIL_KEYS = [
        'password', 'token', 'secret', 'otp', 'code', 'recovery_codes',
        'contact', 'phone', 'address', 'bio', 'note', 'remark', 'incident',
        'reference', 'transaction', 'account_identifier', 'plaintext', 'ciphertext', 'key',
    ];

    public function record(
        string $action,
        ?User $subject = null,
        ?User $actor = null,
        array $details = [],
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        $log = AuditLog::create([
            'actor_user_id' => $actor?->id ?? $request->user()?->id,
            'subject_user_id' => $subject?->id,
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'details' => $this->sanitize($details),
            'created_at' => now(),
        ]);

        $messages = [
            'password.changed' => 'Your account password was changed. If this was not you, review your account security.',
            'password.reset' => 'Your account password was reset. If this was not you, contact an administrator.',
            'trusted_device.revoked' => 'A trusted device was revoked. It will require verification at the next sign-in.',
            'trusted_device.others_revoked' => 'Your other trusted devices were revoked.',
            'account.sessions_and_trust_revoked' => 'An administrator revoked your sessions and trusted devices.',
            'two_factor.reset_by_admin' => 'An administrator reset your two-factor authentication. Complete setup before accessing your workspace.',
            'two_factor.recovery_codes_regenerated' => 'Your recovery codes were regenerated. Previous recovery codes no longer work.',
        ];
        if ($subject && isset($messages[$action])) {
            app(PortalNotifications::class)->security($subject, $messages[$action]);
        }
        return $log;
    }

    private function sanitize(array $details): array
    {
        foreach ($details as $key => $value) {
            $normalized = strtolower((string) $key);

            if (collect(self::FORBIDDEN_DETAIL_KEYS)->contains(fn (string $forbidden): bool => str_contains($normalized, $forbidden))) {
                unset($details[$key]);
            } elseif (is_array($value)) {
                $details[$key] = $this->sanitize($value);
            }
        }

        return $details;
    }
}
