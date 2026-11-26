<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function provisioningUri(User $user): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name', 'FacultyConnect'),
            $user->isAcademicRosterDemo() ? $user->faculty_id : $user->email,
            $user->two_factor_secret,
        );
    }

    public function qrCodeDataUri(User $user): string
    {
        $renderer = new ImageRenderer(new RendererStyle(260, 2), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($this->provisioningUri($user));

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function verifyTotp(User $user, #[\SensitiveParameter] string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->security_version !== $user->security_version || $locked->status === User::STATUS_DEACTIVATED) {
                return false;
            }
            $user->setRawAttributes($locked->getAttributes(), true);

            return $this->verifyTotpLocked($user, $code);
        });
    }

    private function verifyTotpLocked(User $user, string $code): bool
    {
        if (! $user->two_factor_secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $step = $this->google2fa->verifyKeyNewer(
            $user->two_factor_secret,
            $code,
            $user->two_factor_last_used_step ?? 0,
            1,
        );

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_used_step' => $step])->save();

        return true;
    }

    public function generateRecoveryCodes(User $user): array
    {
        $plain = collect(range(1, 8))
            ->map(fn (): string => strtoupper(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'two_factor_recovery_codes' => array_map(fn (string $code): string => $this->hashRecoveryCode($code), $plain),
        ])->save();

        return $plain;
    }

    public function useRecoveryCode(User $user, #[\SensitiveParameter] string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($locked->security_version !== $user->security_version || $locked->status === User::STATUS_DEACTIVATED) {
                return false;
            }
            $user->setRawAttributes($locked->getAttributes(), true);

            return $this->useRecoveryCodeLocked($user, $code);
        });
    }

    private function useRecoveryCodeLocked(User $user, string $code): bool
    {
        $candidate = $this->hashRecoveryCode($code);
        $hashes = $user->two_factor_recovery_codes ?? [];

        foreach ($hashes as $index => $hash) {
            if (hash_equals($hash, $candidate)) {
                unset($hashes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }

    public function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', strtoupper(str_replace(' ', '', trim($code))), (string) config('app.key'));
    }
}
