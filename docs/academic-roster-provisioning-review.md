# Academic roster provisioning review — November 24, 2026 (Asia/Manila)

**Deployment completed after explicit user approval.** The review below is retained as the pre-deployment record; its pending-state statements are historical. The normal migration is applied and 40 missing accounts are created. See [completed deployment results](academic-roster-provisioning-results.md) for final counts, checks and the credentials report location.

## Approval boundary

The user explicitly authorized implementation and disposable-copy testing, and required separate approval before applying a normal database migration or inserting normal accounts. **Neither normal operation has been performed.** Source changes are prepared in this workspace; existing accounts have no roster markers and keep their original onboarding rules.

## Reconciled import

Source: `C:\Users\Charles\Downloads\CCIS-Faculty-Roster-2026-2027.xlsx`, worksheet `FACULTY ROSTER`, 41 teaching entries (30 full-time and 11 part-time). Other worksheet versions and academic support/non-teaching entries are excluded. Source SHA-256: `eb266cf1e20f1a749791d1404e4032f3aa3d259a3ecae706972c3d7a7ed1a355`.

The source supplies neither emails nor official faculty IDs. Generated login IDs use `CCIS-DEMO-2627-NNN`; these are explicitly academic demonstration identifiers, not official UMak employee identifiers. Original source names, designations and employment classifications are preserved. First/middle/last names are parsed without expanding initials or inventing names.

Read-only normal-database reconciliation found **one existing match: Michael C. Olivo, user 45**. That account is preserved exactly, including its current role, credentials and authentication settings. **40 missing accounts** are proposed, all Faculty role. Existing name or generated-ID conflicts stop import. Matching uses normalized full first/last names with middle-initial consistency; ambiguity stops import rather than guessing. Stable roster keys and unique login IDs make the import idempotent.

## Proposed schema

Migration: `database/migrations/2026_11_24_000000_add_academic_roster_provisioning.php`.

- Make `users.email` nullable, retaining its unique index and all existing values.
- Add `academic_roster_accounts`, with unique roster key and user ID, administrator provenance, original source name, classification, source checksum and creation timestamp.
- No existing user receives a marker, role change, password change or verification change.
- The standard administrator creation endpoint still requires a unique email. There is no new HTTP creation route, public registration route or self-service flag.
- Rollback refuses to remove the schema while email-less accounts remain; it never fills placeholder emails.

## Authentication and authorization

Only a Faculty account with no email, the generated demonstration-ID prefix, and an explicit persisted roster-provisioning marker can skip the unavailable email-verification step. An ID prefix alone cannot enable the exception. Markers are not mass assignable on User.

The new accounts are active for directory membership but remain gated by temporary-password replacement and mandatory authenticator-app TOTP enrollment. No activation or TOTP verification timestamp is fabricated. Temporary credentials expire under the existing 48-hour policy, starting at approved import time. TOTP QR labels use each generated login ID; secrets and recovery codes are generated independently by the existing enrollment code. Existing session/security-version protections, login throttles and role/private-record policies remain in force. No 2FA exemption was implemented or enabled.

The CLI command authenticates an existing Administrator with hidden password entry before provisioning. The service also checks active Administrator role, verified email, completed 2FA and no outstanding password replacement. Import is serialized with the existing account-management lock. Any conflict or credential-report failure rolls back account and audit inserts.

The importer does not send invitations, verification messages, OTPs, SMS or welcome emails, and never substitutes the project owner's university address. Invitation and verification sends are rejected for these email-less demonstration accounts. Standard-account behavior is retained.

## Backup and disposable-copy evidence

Normal identity verified: `ccis_local_20261018_164245`, loopback port 3307, forced recovery zero.

Protected evidence folder:
`C:\Users\Charles\FacultyConnect-Recovery\recurring-backups\academic-roster-preflight-20261124-134109`

It contains `main-before.sql` and `backup-restoration-verified.json`. Windows ACL inheritance is disabled; only Charles, SYSTEM and local Administrators have access. This folder is outside the project and its public document root.

The full 24-table backup restored into the separate disposable schema `fc_roster_restore_20261124_134109`. Restored tables passed health checks and 18 stable table fingerprints matched the source. Session/cache/queue tables are volatile and were not used to claim stable-row preservation.

On this copy only, the proposed migration ran, one existing match was preserved, and 40 accounts were inserted. All 40 independently generated temporary passwords passed the real login controller and redirected to first-login password replacement. Hashes, role, null email/verification, uncompleted TOTP and unique generated identifiers were checked. QR identities and independently generated TOTP secrets were checked without pre-enrolling the accounts. Repeating the importer created zero accounts. Existing copy users and financial/history table fingerprints remained unchanged. No historical contribution memberships were generated. No external messages were sent. The normal database's stable rows remained unchanged.

Disposable credentials were kept only in the verification process's memory; no usable normal-account credentials exist yet. The restored schema is retained as isolated evidence; it has no web server or tunnel pointing to it. It is not a live presentation dataset.

## Credentials after approval

Proposed report:
`C:\Users\Charles\FacultyConnect-Recovery\recurring-backups\academic-roster-preflight-20261124-134109\new-faculty-credentials.json`

This file **does not exist yet**. Approved provisioning creates it exclusively under the checked restrictive directory ACL. It contains credentials for newly inserted accounts only, with names, generated login IDs, unique temporary passwords, role, status and expiry. Database rows store password hashes only. Existing credentials are not exported or reset. No password appears in console output, this review, application logs or chat.

After approval, Charles can retrieve the report locally in File Explorer under his Windows account, or an authorized Windows administrator can open it. It must not be copied into the project, public storage, email or a shared link.

## Operator entry points

`powershell -NoProfile -ExecutionPolicy Bypass -File tools/Export-AcademicRoster.ps1` extracts only the reviewed teaching sheet and validates counts.

`php artisan facultyconnect:academic-roster` performs read-only reconciliation. It does not migrate or insert accounts.

After separate approval, apply only the named migration, then run `facultyconnect:academic-roster --apply` with the chosen existing Administrator ID and protected report path. Administrator password entry is hidden. The normal-database identity guard and explicit confirmation remain required. Recheck source checksum and reconciliation immediately before applying, and take a fresh protected snapshot if the live data changed after review.

## Verification scope

Automated tests exercise bulk import/idempotency, credential hashing/uniqueness, existing-account preservation, rollback, conflicts, unauthorized provisioning, standard email verification, public registration denial, full first-login password replacement and real TOTP confirmation, subsequent TOTP challenge, role restrictions, private payment/notification isolation and directory visibility. All tests use isolated SQLite memory databases, with no real messages. Actual faculty enrollment and sign-in on personal devices have not occurred.

Final suite: **236 tests passed, 2,839 assertions**. PHP syntax checks passed for the operator command, migration and backup/copy-verification scripts. Normal migration status was independently checked and remains **Pending**.

Approval requested: apply this one migration to the normal database and create the 40 reconciled missing Faculty accounts, preserving the existing match and all other existing accounts and financial history.
