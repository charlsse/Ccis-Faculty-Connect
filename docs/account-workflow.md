# Final account and security workflow

This workflow supersedes the old password-creation activation links. Public registration and the old /activate routes are unavailable.

## Operator setup

Run from the project root:

```powershell
php artisan facultyconnect:setup-admin
```

Enter Sir Olivo's actual first/last name, official email, employee ID, department and position. No personal details are prefilled or guessed. New accounts require a strong password entered twice using hidden terminal prompts and a creation confirmation. The account signs in using the ordinary login page, explicitly verifies email, then enrolls TOTP. Email is not automatically verified.

If email and employee ID both identify the same existing account, the command asks for explicit promotion confirmation. It preserves that account's password, profile, payments and valid TOTP enrollment, and revokes sessions/browser trust. Conflicting identifiers stop the operation. If any administrator already exists the command refuses to bootstrap another. The command and last-admin updates share a singleton database row lock and transactions.

For targeted operator recovery:

```powershell
php artisan facultyconnect:recover-admin
```

Enter matching administrator email and employee ID, choose password reset / 2FA reset / pending invitation resend, then confirm. A completed account cannot receive fresh initial credentials. Email actions use the configured provider; this command is never a public route.

No real administrator was created during implementation. The operator must supply the real details above.

## Permissions

| Role | Implemented permission |
| --- | --- |
| Administrator / President | Account search, creation, role/status changes, account security controls and per-account audit history; directory; announcements; collections |
| Secretary | Official directory (name, email, department, position only); manage announcements as existing club records; own profile/security/payments |
| Treasurer | Create/update/delete collections, view all collection records and dashboard collection totals; own profile/security |
| Faculty Member | Own profile/security/payments; published announcements |
| Legacy Staff | Same personal access baseline as faculty; no officer or admin privileges |

The fixed role map is in config/roles.php. Permissions are checked on routes and in controllers as well as in views. Secretary/Treasurer do not receive account-management privileges. No role editor was added. Inventory, dedicated reports and other coming-soon modules remain placeholders; no new business modules were built.

The last usable administrator cannot be demoted/deactivated. Another active administrator must have verified email, completed TOTP and a permanent password. Role/status changes require the acting administrator's current password and invalidate the target's sessions and browser trust. Explicit 2FA recovery can require an administrator to enroll again; it does not remove the mandatory requirement.

## Invitation and onboarding

1. Create Account stores a pending account with an inaccessible random password. No email is sent.
2. Send Invitation (with administrator password confirmation), or Create & Send Invitation, generates a new random 24-character temporary password.
3. Only a Laravel password hash is saved. Default validity is 48 hours.
4. A responsive green FacultyConnect email and plain-text alternative include greeting, Sir Olivo's invitation, role, email username, temporary password, expiry, support contact and instructions.
5. The button points only to APP_URL/login. Credentials are never URL parameters.
6. The member signs in at the ordinary login page, replaces the temporary password with a different strong password, requests/confirms email verification, then enrolls and confirms Google Authenticator.
7. Recovery codes are shown once. Browser trust is optional and unchecked. After all checks, dashboard access is granted.

Distinct fields track invitation acceptance/failure and date, temporary expiry, required password replacement, replacement completion, email verification, TOTP confirmation, and onboarding completion. Existing active accounts retain their passwords and TOTP enrollment and only complete missing security steps.

Pending accounts cannot use profile, collections, directory or admin routes to bypass onboarding; JSON requests to these protected routes also enforce the restriction. Reissuing or replacing temporary credentials invalidates prior onboarding sessions using the security version. Expired/unsent credentials cannot log in. A failed invitation remains retryable and its temporary password is invalidated.

Invitations are sent synchronously inside a per-account transaction/lock. They do not implement ShouldQueue and are not stored in jobs or failed_jobs. Log/array/custom transports are refused outside tests to avoid recording credentials; supported transports are SMTP, sendmail, SES, Postmark and Resend. SMTP/provider acceptance is not inbox-delivery confirmation. A transport/commit failure can leave an unusable emailed credential; retry creates a new one. Avoid mail-content logging in external provider monitoring.

## Authentication details

Password is always required; the old Remember Me checkbox was removed and submitted remember flags are ignored. Existing remember-cookie authentication is rejected. A successful password/TOTP login or enrollment establishes the normal session.

PragmaRX Google2FA generates/verifies OTPs; the User encrypted cast protects the TOTP secret. The manual key is the same secret, not another stored copy. Enrollment QR codes are not shown again after confirmation. The integration passes an initial previous step of 0 so PragmaRX returns the matched time step (a null previous step returns boolean true). Row locks plus a newer-step check prevent replay; recovery-code consumption is also locked and single-use. Login, challenge and setup endpoints are rate-limited. Password-stage challenges expire after ten minutes and are invalidated by security-version changes.

Browser tokens remain account-bound HMAC hashes with protected HttpOnly/SameSite cookies. Production cookies require HTTPS. The fixed 20-day expiry is not extended by password login; tokens rotate on use. New trust requires TOTP verification or first enrollment and explicit consent. Recovery codes restore access but cannot issue new browser trust.

Normal logout preserves future browser trust. Log out and forget revokes it. Security Settings distinguish revoking future trust from terminating sessions. Password changes/resets, account deactivation, role/status changes and 2FA resets revoke relevant sessions/trust. Security actions keep current-password confirmation.

## Encryption and privacy

AES-256-CBC and APP_KEY were preserved. TOTP, contact number, bio, payment reference and notes retain authenticated encrypted storage. Recovery codes remain individually HMAC-hashed inside the encrypted collection. Passwords are one-way hashed. Legacy invitation-token columns are retained for record compatibility but new invitations no longer use activation tokens; reset/email verification tokens retain their existing protection.

Private profile/payment fields are excluded from general model serialization. Authorized Blade pages access needed values explicitly. Sensitive form input is not flashed; invitation credentials never enter sessions or audits. Private responses use no-store caching. Delivery failures record exception class only, not message bodies. Profile photo validation and generated storage filenames remain in place.

The existing conversion command is resumable and supports dry-run/verification. It now refuses plaintext conversion without --allow-plaintext after review, recognizes damaged Laravel payload prefixes, checks rows for concurrent changes under locks, and decrypt-verifies changed fields before committing. Production conversion requires maintenance mode. Stop queue workers and other writers during conversion. An unreadable envelope may indicate corruption or missing/wrong keys; do not treat it as recoverable plaintext.

## Backup and deployment

Use the existing private backup command before migrations. Serve only the public directory. Keep .env, storage, backups and keys inaccessible over HTTP.

```powershell
php artisan down
php artisan facultyconnect:backup-database --label=pre-account-workflow
php artisan migrate --force
php artisan facultyconnect:protect-data --verify
php artisan up
```

The onboarding migration adds columns and the shared lock row; it does not rewrite existing user records. For a genuinely legacy plaintext database, review a dry run against a trusted backup and use:

```powershell
php artisan facultyconnect:protect-data --dry-run
php artisan facultyconnect:protect-data --allow-plaintext --force
php artisan facultyconnect:protect-data --verify
```

See data-protection.md for key backup and database restoration. Never regenerate APP_KEY to troubleshoot ciphertext. A pre-migration backup may contain plaintext and needs restricted encrypted storage.

## Environment and email

Placeholder configuration only:

```dotenv
APP_URL=https://facultyconnect.example.edu
APP_ENV=production
APP_DEBUG=false
APP_CIPHER=AES-256-CBC
APP_KEY=
APP_PREVIOUS_KEYS=
TEMPORARY_PASSWORD_HOURS=48
TRUSTED_DEVICE_DAYS=20
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=FacultyConnect
FACULTYCONNECT_SUPPORT_EMAIL=
```

Keep the actual existing key in the protected server environment; the empty value above is documentation, not an instruction to erase it. Configure the existing SMTP provider and TLS settings. Set APP_URL to the actual reachable login origin. For local HTTP development, keep secure-cookie configuration compatible with that URL. No queue worker is required for temporary-credential invitations. Existing other queue configuration is preserved.

## Verification and demonstration

Tests use SQLite :memory: and fake notification delivery. tests/bootstrap.php rejects cached application configuration before any test starts; TestCase also refuses any database other than SQLite :memory:. Use:

```powershell
php artisan config:clear
php artisan test
php packages/bin/pint --test app config database routes tests bootstrap/app.php
composer validate --no-check-publish
```

For a demonstration, use an isolated database and controlled test inbox. Establish the first admin with actual operator-supplied details. Show email verification and TOTP enrollment. Create a faculty account, choose Secretary or Treasurer, send credentials, then walk through password replacement, email verification, TOTP, recovery codes and optional browser trust. Show that direct dashboard access is blocked during setup, role access differs, normal logout retains trust, and forgetting the browser requires TOTP. Show the value-free encryption verification command, and demonstrate account status and security actions.

During development verification no real invitation was sent, no administrator was guessed/created, and APP_KEY was not changed. SMTP inbox delivery and real mobile scanning are operator demonstration steps; automated tests cover generated emails, QR-page rendering and OTP validation.

Final verification: 67 tests passed with 470 assertions; Laravel Pint, Composer validation, Blade compilation and route registration passed. No separate static-analysis tool is configured. Interactive browser layout inspection, real inbox delivery and physical-phone scanning were not performed.

The local MySQL database was backed up before the additive migration to storage/app/private/backups/facultyconnect-pre-account-workflow-20260906-234410.sql. The onboarding migration is applied locally, not deployed to production. Post-test database checks still show both original active faculty accounts (IDs 1 and 44), no administrator, and encryption verification reports zero migration needs, corrupt values or failures. Keep the private backup protected and do not publish it.

## Changed files

- Configuration/schema: .env.example; config/account-security.php; config/roles.php; database/migrations/2026_09_06_000400_add_account_onboarding.php.
- Operator commands/services: app/Console/Commands/SetupInitialAdministrator.php; app/Console/Commands/RecoverAdministrator.php; app/Services/AdministratorService.php; app/Services/InvitationService.php; app/Services/TwoFactorService.php; app/Services/DataProtectionService.php; app/Console/Commands/ProtectSensitiveData.php.
- Request enforcement: bootstrap/app.php; routes/web.php; app/Providers/AppServiceProvider.php; app/Http/Middleware/EnsureAccountIsUsable.php; app/Http/Middleware/EnsureOnboarding.php; app/Http/Middleware/EnsurePermission.php; app/Http/Middleware/PrivateResponse.php.
- Controllers/models: app/Http/Controllers/AuthController.php; OnboardingController.php; TwoFactorController.php; FacultyMemberController.php; DirectoryController.php; PaymentController.php; DashboardController.php; AnnouncementController.php; ProfileController.php (all in that controllers directory); app/Models/User.php; app/Models/Payment.php.
- Mail: app/Notifications/AccountInvitation.php; resources/views/mail/invitation.blade.php; resources/views/mail/invitation-text.blade.php.
- Interface: resources/views/auth/login.blade.php; change-temporary-password.blade.php; verify-email.blade.php; two-factor-challenge.blade.php; two-factor-setup.blade.php; resources/views/layouts/dashboard.blade.php; resources/views/dashboard/index.blade.php; dashboard/members/create.blade.php, index.blade.php, show.blade.php, directory.blade.php, partials/form.blade.php; dashboard/payments/index.blade.php; dashboard/announcements/index.blade.php and show.blade.php.
- Tests: phpunit.xml; tests/bootstrap.php; tests/TestCase.php; tests/Feature/AccountWorkflowTest.php; DataProtectionTest.php; FacultyAuthTest.php; MemberRecordsTest.php; SecuritySettingsTest.php; TrustedDeviceTest.php.
- Documentation: README.md; docs/data-protection.md; docs/account-workflow.md.

Existing .env credentials and APP_KEY were not edited for this workflow.
