<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use LogicException;

class DataProtectionService
{
    public const STATE_EMPTY = 'empty';

    public const STATE_ENCRYPTED = 'encrypted';

    public const STATE_CORRUPTED = 'corrupted';

    public const STATE_PLAINTEXT = 'plaintext';

    public function paymentReferenceLookup(string $reference): string
    {
        return hash_hmac('sha256', $this->normalizePaymentReference($reference), $this->lookupKey());
    }

    public function normalizePaymentReference(string $reference): string
    {
        return mb_strtoupper(Str::squish(trim($reference)), 'UTF-8');
    }

    public function classify(?string $value): string
    {
        if ($value === null || $value === '') {
            return self::STATE_EMPTY;
        }

        try {
            Crypt::decryptString($value);

            return self::STATE_ENCRYPTED;
        } catch (DecryptException) {
            return $this->resemblesLaravelPayload($value)
                ? self::STATE_CORRUPTED
                : self::STATE_PLAINTEXT;
        }
    }

    public function decrypt(string $value): string
    {
        return Crypt::decryptString($value);
    }

    public function encrypt(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public function resemblesLaravelPayload(string $value): bool
    {
        // A damaged/truncated Laravel envelope must never be treated as plain text.
        if (str_starts_with($value, 'eyJpdiI6') || str_starts_with($value, '{"iv":')) {
            return true;
        }
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac'])
            && is_string($payload['iv'])
            && is_string($payload['value'])
            && is_string($payload['mac']);
    }

    private function lookupKey(): string
    {
        $configured = trim((string) config('data-protection.lookup_key'));

        if ($configured !== '') {
            if (str_starts_with($configured, 'base64:')) {
                $decoded = base64_decode(substr($configured, 7), true);

                if ($decoded === false || strlen($decoded) < 32) {
                    throw new LogicException('FACULTYCONNECT_LOOKUP_KEY must contain at least 32 decoded bytes.');
                }

                return $decoded;
            }

            if (strlen($configured) < 32) {
                throw new LogicException('FACULTYCONNECT_LOOKUP_KEY must contain at least 32 characters.');
            }

            return $configured;
        }

        $applicationKey = (string) config('app.key');
        $keyMaterial = str_starts_with($applicationKey, 'base64:')
            ? base64_decode(substr($applicationKey, 7), true)
            : $applicationKey;

        if (! is_string($keyMaterial) || strlen($keyMaterial) < 32) {
            throw new LogicException('A valid APP_KEY is required to derive the FacultyConnect lookup key.');
        }

        return hash_hkdf('sha256', $keyMaterial, 32, 'FacultyConnect payment reference lookup v1');
    }
}
