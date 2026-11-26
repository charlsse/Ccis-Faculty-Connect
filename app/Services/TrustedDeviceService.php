<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Support\TrustedDeviceResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class TrustedDeviceService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function issue(User $user, Request $request, ?int $renewedFromId = null): TrustedDeviceResult
    {
        $token = Str::random(64);
        $expiresAt = now()->addDays(config('trusted-device.days'));
        $device = $user->trustedDevices()->create([
            'token_hash' => $this->hash($token),
            'device_name' => $this->deviceName((string) $request->userAgent()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'ip_address' => $request->ip(),
            'first_trusted_at' => now(),
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $isRenewal = $renewedFromId !== null
            && $user->trustedDevices()->whereKey($renewedFromId)->exists();
        $this->auditLogger->record($isRenewal ? 'trusted_device.renewed' : 'trusted_device.trusted', $user, $user, [
            'trusted_device_id' => $device->id,
            'previous_trusted_device_id' => $isRenewal ? $renewedFromId : null,
            'device_name' => $device->device_name,
            'expires_at' => $expiresAt->toIso8601String(),
        ], $request);

        return new TrustedDeviceResult(true, $device, $this->makeCookie($device, $token));
    }

    public function consume(User $user, Request $request): TrustedDeviceResult
    {
        $payload = $request->cookie(config('trusted-device.cookie'));

        if (! is_string($payload) || $payload === '') {
            return new TrustedDeviceResult(false);
        }

        [$id, $token] = array_pad(explode('.', $payload, 2), 2, null);
        if (! ctype_digit((string) $id) || ! is_string($token) || strlen($token) < 32) {
            $this->rejected($user, $request, 'malformed_cookie');

            return new TrustedDeviceResult(false, forgetCookie: true);
        }

        $device = TrustedDevice::find((int) $id);
        if (! $device) {
            $this->rejected($user, $request, 'unknown_device');

            return new TrustedDeviceResult(false, forgetCookie: true);
        }

        if ($device->user_id !== $user->id) {
            $device->forceFill(['revoked_at' => $device->revoked_at ?? now()])->save();
            $this->rejected($user, $request, 'user_mismatch');

            return new TrustedDeviceResult(false, $device, forgetCookie: true);
        }

        if (! $device->isValid()) {
            if (! $device->revoked_at) {
                $device->forceFill(['revoked_at' => now()])->save();
            }
            $this->rejected($user, $request, $device->expires_at->isPast() ? 'expired' : 'revoked');

            return new TrustedDeviceResult(false, $device, forgetCookie: true);
        }

        if (! hash_equals($device->token_hash, $this->hash($token))) {
            $device->forceFill(['revoked_at' => now()])->save();
            $this->rejected($user, $request, 'token_mismatch');

            return new TrustedDeviceResult(false, $device, forgetCookie: true);
        }

        if (! hash_equals((string) $device->user_agent, mb_substr((string) $request->userAgent(), 0, 1000))) {
            $device->forceFill(['revoked_at' => now()])->save();
            $this->rejected($user, $request, 'user_agent_changed');

            return new TrustedDeviceResult(false, $device, forgetCookie: true);
        }

        $rotatedToken = Str::random(64);
        $device->forceFill([
            'token_hash' => $this->hash($rotatedToken),
            'last_used_at' => now(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'ip_address' => $request->ip(),
        ])->save();
        $this->auditLogger->record('trusted_device.used', $user, $user, [
            'trusted_device_id' => $device->id,
            'device_name' => $device->device_name,
            'expires_at' => $device->expires_at->toIso8601String(),
        ], $request);

        return new TrustedDeviceResult(true, $device, $this->makeCookie($device, $rotatedToken));
    }

    public function current(User $user, Request $request): ?TrustedDevice
    {
        $payload = $request->cookie(config('trusted-device.cookie'));
        [$id, $token] = is_string($payload) ? array_pad(explode('.', $payload, 2), 2, null) : [null, null];

        if (! ctype_digit((string) $id) || ! is_string($token)) {
            return null;
        }

        $device = TrustedDevice::whereKey((int) $id)->where('user_id', $user->id)->first();

        return $device
            && $device->isValid()
            && hash_equals($device->token_hash, $this->hash($token))
            && hash_equals((string) $device->user_agent, mb_substr((string) $request->userAgent(), 0, 1000))
            ? $device
            : null;
    }

    public function revoke(User $user, TrustedDevice $device, Request $request, string $reason = 'user_revoked'): bool
    {
        abort_unless($device->user_id === $user->id, 404);

        if ($device->revoked_at) {
            return false;
        }

        $device->forceFill(['revoked_at' => now()])->save();
        $this->auditLogger->record('trusted_device.revoked', $user, $user, [
            'trusted_device_id' => $device->id,
            'device_name' => $device->device_name,
            'reason' => $reason,
        ], $request);

        return true;
    }

    public function revokeCurrent(User $user, Request $request, string $reason = 'logout_and_forget'): bool
    {
        $device = $this->current($user, $request);

        return $device ? $this->revoke($user, $device, $request, $reason) : false;
    }

    public function revokeAll(User $user, string $reason, ?User $actor = null, ?Request $request = null): int
    {
        $count = $user->trustedDevices()->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);

        if ($count > 0) {
            $this->auditLogger->record('trusted_device.all_revoked', $user, $actor, [
                'count' => $count,
                'reason' => $reason,
            ], $request);
        }

        return $count;
    }

    public function revokeOthers(User $user, Request $request): int
    {
        $currentId = $this->current($user, $request)?->id;
        $query = $user->trustedDevices()->whereNull('revoked_at');
        if ($currentId) {
            $query->whereKeyNot($currentId);
        }
        $count = $query->update(['revoked_at' => now(), 'updated_at' => now()]);

        if ($count > 0) {
            $this->auditLogger->record('trusted_device.others_revoked', $user, $user, ['count' => $count], $request);
        }

        return $count;
    }

    public function forgetCookie(): SymfonyCookie
    {
        return Cookie::forget(
            config('trusted-device.cookie'),
            config('trusted-device.path'),
            config('trusted-device.domain'),
        );
    }

    private function makeCookie(TrustedDevice $device, string $token): SymfonyCookie
    {
        return Cookie::make(
            config('trusted-device.cookie'),
            $device->id.'.'.$token,
            max(1, (int) ceil(now()->diffInMinutes($device->expires_at))),
            config('trusted-device.path'),
            config('trusted-device.domain'),
            (bool) config('trusted-device.secure'),
            true,
            false,
            config('trusted-device.same_site'),
        );
    }

    private function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }

    private function rejected(User $user, Request $request, string $reason): void
    {
        $this->auditLogger->record('trusted_device.rejected', $user, $user, ['reason' => $reason], $request);
    }

    private function deviceName(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Microsoft Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Google Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Unknown Browser',
        };
        $platform = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return $browser.' on '.$platform;
    }
}
