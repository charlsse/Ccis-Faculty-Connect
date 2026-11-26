# FacultyConnect Data Protection

FacultyConnect uses Laravel authenticated AES-256-CBC encryption to protect reversible sensitive information such as two-factor authentication secrets and confidential faculty data. Passwords, recovery codes, invitation tokens, password-reset tokens, and trusted-device tokens use appropriate one-way hashing because the system does not need to recover their original values. Encryption keys are maintained in the protected server environment and are not stored in the application database or source-code repository.

## Protected data

Laravel's authenticated encrypted payload format provides confidentiality and integrity protection for:

| Table | Field | Application behavior |
| --- | --- | --- |
| `users` | `contact_number` | Decrypted only through the authorized `User` model |
| `users` | `bio` | Decrypted only through the authorized `User` model |
| `users` | `two_factor_secret` | Decrypted only for enrollment and TOTP validation |
| `users` | `two_factor_recovery_codes` | Encrypted collection containing one-way HMAC hashes |
| `payments` | `reference_no` | Decrypted for authorized collection views |
| `payments` | `notes` | Decrypted for authorized collection views |

The `payments.reference_no_lookup` column is a normalized HMAC-SHA-256 blind index. It supports exact duplicate detection without deterministic encryption or recovery of the reference number. Contact numbers, biographies, and payment notes are intentionally not searchable in SQL. Names, official email addresses, faculty IDs, departments, positions, roles, statuses, amounts, and dates remain searchable and are protected by server-side authorization rather than reversible encryption.

Passwords remain bcrypt/Argon-compatible Laravel hashes. Recovery codes are individually HMAC-hashed and single-use. New invitations contain temporary passwords stored only as password hashes; legacy invitation and pending-email tokens use SHA-256 hashes, Laravel password-reset tokens use Laravel's password hasher, and trusted-device tokens use HMAC-SHA-256. Hashing is appropriate when only comparison is required; encryption is required for information the authorized application must display again.

## Key management

Required environment configuration:

```env
APP_CIPHER=AES-256-CBC
APP_KEY=base64:replace-with-a-secure-32-byte-laravel-key
APP_PREVIOUS_KEYS=
FACULTYCONNECT_LOOKUP_KEY=
```

Never commit or display the real keys. Do not run `php artisan key:generate` against an existing encrypted installation. Losing every copy of the active and previous encryption keys makes encrypted data unrecoverable. Back up production keys separately from database backups in a restricted secret manager or encrypted offline recovery store.

`FACULTYCONNECT_LOOKUP_KEY` should be a separate random value of at least 32 bytes in production. A `base64:` value is supported. If it is blank, FacultyConnect derives a purpose-specific lookup key from `APP_KEY` with HKDF. Changing the lookup key requires rebuilding every payment-reference lookup using the data-protection command while the old lookup key is still available.

Laravel 12 supports comma-separated `APP_PREVIOUS_KEYS`. During a planned encryption-key rotation, retain the old key there so existing ciphertext can be decrypted, deploy the new active key, and run a controlled re-encryption process before retiring old keys. Key rotation is an operational procedure and is not performed automatically by this feature.

## Backup and conversion

Create and verify a backup before changing schema or data:

```bash
php artisan facultyconnect:backup-database --label=pre-encryption
```

The command passes the password through the child-process environment rather than command-line output and writes the SQL dump beneath `storage/app/private/backups`. It prints the private path and SHA-256 checksum, never credentials or row data. The dump may contain pre-conversion plaintext, so immediately copy it to encrypted, access-controlled backup storage and test restoration on an isolated system.

Deployment sequence:

```bash
php artisan down
php artisan facultyconnect:backup-database --label=pre-encryption
php artisan migrate --force
php artisan facultyconnect:protect-data --dry-run --chunk=100
php artisan facultyconnect:protect-data --chunk=100 --allow-plaintext --force
php artisan facultyconnect:protect-data --verify --chunk=100
php artisan up
```

Omit `--force` outside production. Conversion of candidate plaintext requires explicit --allow-plaintext after review against a trusted backup. Production conversion requires maintenance mode and paused workers. The converter reads rows in ID chunks, distinguishes valid Laravel ciphertext from plaintext and corrupted ciphertext, skips already encrypted values, normalizes nullable empty values, and updates each record atomically. It reports table names, record IDs, statuses, and counts only. Re-running it after interruption is safe. `--dry-run` and `--verify` never alter protected records.

There is intentionally no automatic decryption rollback. Randomized encrypted data may exceed old column sizes, and an incorrect or unavailable key must never silently corrupt it. If conversion cannot be completed safely, place the application in maintenance mode and restore the verified pre-conversion database backup together with the matching application key and code release.

For a backup held in the private backup directory, an explicit local recovery command is available:

```bash
php artisan facultyconnect:restore-database facultyconnect-pre-encryption-YYYYMMDD-HHMMSS.sql --force
```

This replaces the configured MySQL database contents, so confirm the filename, database configuration, checksum, and maintenance mode before running it. After restoring a pre-encryption backup, redeploy the matching code or rerun the schema migration, conversion, and verification sequence.

## Production checklist

1. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_CIPHER=AES-256-CBC`, and a valid protected `APP_KEY`.
2. Configure a separate `FACULTYCONNECT_LOOKUP_KEY` and securely back up both keys.
3. Restrict `.env`, `storage`, database, phpMyAdmin, dump, and backup access to authorized operators.
4. Use TLS, secure session/trusted-device cookies, least-privilege database credentials, and encrypted backup storage.
5. Create and restore-test a pre-conversion backup.
6. Run migrations, dry-run conversion, conversion, and verification in maintenance mode.
7. Run `php artisan test`, then exercise login, 2FA, recovery codes, trusted devices, invitations, password resets, profile changes, and collection access.
8. Review logs and audit records for metadata only; never record decrypted values, keys, OTPs, codes, or tokens.
9. Keep the active key available to every application/queue node and retain needed previous keys during planned rotation.

## Safe academic demonstration

Use a local or isolated demonstration database with non-production sample data:

1. Sign in as the sample faculty member and open the profile page. The edit field shows the readable contact number while the summary masks its middle digits.
2. In phpMyAdmin, select only that row's `contact_number`; the value is a Laravel encrypted payload rather than the number. Do not publish a complete production ciphertext or dump.
3. Show that `two_factor_secret` is ciphertext in MySQL, then complete a login with the authenticator-generated OTP.
4. Compare storage types without revealing values: `password`, individual recovery-code values, invitation/reset tokens, and `trusted_devices.token_hash` are one-way hashes.
5. Run `php artisan facultyconnect:protect-data --verify`; only IDs and aggregate status are shown.
6. Sign in as a different faculty account and demonstrate that server-side middleware blocks the administrator member route and limits collections to that account.

Encryption reduces exposure from database disclosure, exports, and backups; it does not replace authorization, operating-system security, TLS, secure sessions, or careful endpoint design. An attacker who controls both the running application and its active key can decrypt data available to that application.
