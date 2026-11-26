# Approved in-app notification deployment

## Deployment result

The reviewed additive notification schema is deployed, durable in-app delivery is active, and the automatic maintenance task is enabled. Payment email remains disabled in the unchanged application configuration, and the Windows maintenance action additionally passes `--no-email`.

No new external payment session, real email, Live Mode payment or withdrawal was created. Existing Xendit credentials, webhook configuration, ngrok routing, APP_KEY, passwords, 2FA, roles, database connection settings and normal application-account privileges were not changed.

## Reviewed migrations applied

| Migration | Applied change and verification |
| --- | --- |
| `2026_11_20_000000_add_payment_notification_delivery` | Added `event_key`, `context`, `email_state`, `email_attempts`, `email_next_at`, `email_delivered_at` to main `water_notices`. Verified the unique event-key constraint and email-state index. Existing notice data/read states were preserved. |
| `002_create_notification_outbox` | Created `notification_outbox` in the existing private demo SQLite ledger, with event-key primary key, attempt ID, payload, creation/delivery timestamps and delivered-at index. Existing demo payment records were preserved. |

Both files were read against `docs/payment-notification-schema-review.md` and fingerprinted before backup restoration and deployment. Their hashes were checked again immediately before execution. Both migration records are present in the existing main migration repository. The explicit SQLite migration is recorded there instead of creating an additional unreviewed SQLite migration-repository table. No unrelated pending migration was run.

The application account's lack of ALTER privilege was preserved. The approved administrator credentials were read directly from their protected existing file, used in memory for backup restoration and the temporary schema connection, then discarded. No credential value was printed, logged, copied into another file, changed or granted to the application account. The temporary privileged connection was purged; `.env` and application grants matched their pre-deployment fingerprints.

## Fresh backup and isolated restoration

Target identity was verified as `ccis_local_20261018_164245` on `127.0.0.1:3307`. MariaDB reported forced recovery **0** and read-only mode **0**.

A fresh protected 122,898-byte SQL backup was restored successfully to the new isolated schema `fc_notify_restore_20261118_162348`. All **24 tables** were restored and passed extended table checks. All **18 non-transient tables** matched source row fingerprints; the source remained unchanged. Session/cache/job tables were restored and checked, but excluded from source-equality comparisons because they can change during normal operation. No existing schema was overwritten or deleted.

Backup SHA-256:

`b9100bccff711a22cf209292b6e3895a6ae0fff4ddbd87a99b8f149fcfda9037`

Backup, SQLite backup and detailed verification manifests are under:

`C:/Users/Charles/FacultyConnect-Recovery/recurring-backups/notification-deployment-20261118-162348`

ACL verification showed access limited to Charles, SYSTEM and Administrators. The isolated restored database was retained as verification evidence.

## Preservation checks

All rows/fingerprints remained unchanged in **ten protected main tables**: users, payments, water_collections, water_members, water_submissions, water_receipts, water_payment_accounts, audit_logs, water_reference_claims and water_settings. Existing demo attempt records also matched the pre-deployment SQLite backup exactly. Original notice contents and read states matched the pre-migration backup fingerprints; only new delivery records and their test metadata were exercised.

Actual verified receipts remain **2**, totaling **PHP 1,000.00**, with **zero receipt/member/submission/amount linkage issues**. The separate demo ledger remains **2 confirmed and 1 expired**. Simulation notices explicitly state no funds collected; demo completion did not change actual collection totals, receipts, accounts, obligations or withdrawable balances.

## Actual installed in-app delivery verification

The normal application account delivered the **three persisted demo terminal events** into **three unique authorized in-app notices**. No privileged credential was used for runtime delivery.

The installed database has one active authorized Treasurer. Both existing confirmed test payments were made by that Treasurer account. Consequently, payer and Treasurer are the same physical recipient for those events; deduplication correctly produced one alert per event/user. The expired event notified its affected payer. No duplicate role alerts were created. Separate faculty/Treasurer recipient behavior was verified in isolated integration tests, without creating an external checkout.

Installed-database/application checks passed for:

- Notification presence in the authorized recipient's portal entries and feed/controller output.
- Correct test-payment classification, transaction destination and disabled email state.
- Owner marking a newly delivered notice read and the feed unread count decreasing by exactly one.
- Another user attempting to mark that notice read having no effect.
- Restoring the newly exercised notice's original read/unread state after verification.
- Simulating a lost outbox delivery acknowledgment and replaying it: the acknowledgment recovered, notice count/event keys stayed unchanged, and no duplicate alert or financial mutation occurred.
- A subsequent maintenance run delivering zero additional events.

These were actual persisted notification tests through the installed database and application services/controller. They were not a browser sign-in or visual interaction test, and did not bypass or alter authentication/2FA.

## Worker and automated regression verification

Windows task **FacultyConnect Payment Synchronization** was paused during schema deployment, then re-enabled and executed successfully with **LastTaskResult 0**. It runs at Charles's sign-in and every minute, ignores overlapping instances, and has a five-minute execution limit. Its action is:

`php artisan payments:maintain --no-email`

It reconciles existing unresolved test sessions, delivers/retries persisted events, and runs configured reminders/actual target checks. It does not create checkout sessions or enable fund transfers. Charles must be signed in and the computer/database running; this is workstation scheduling, not an always-on hosted service.

The full post-deployment automated suite passed: **224 tests, 2,283 assertions, zero failures**. Relevant tests cover separate faculty/Treasurer recipients, manual QR proof acknowledgment and Treasurer notification, approval/rejection and correct receipt behavior, collection publication, owner-only history/receipts, callback/capture validation, duplicate/out-of-order delivery, transaction rollback, notification database failure/recovery, reminders, email retry with fakes, unread counts and permission revocation. Write-producing business/payment tests use guarded isolated in-memory databases and provider/mail mocks. No real QR submission, approval, rejection or external payment was performed against the main financial records for testing.

Homepage and login both returned HTTP 200 after activation.

## Files and operational changes in this approval phase

- Added `tools/verify-notification-deployment-backup.php`, `tools/deploy-approved-notification-schema.php` and `tools/verify-live-notification-delivery.php` for guarded backup, deployment and installed verification.
- Extended `tools/audit-payment-synchronization.php` with recipient-configuration counts.
- Applied only the two reviewed migrations listed above and recorded their execution.
- Created the fresh protected backup, isolated restoration and evidence manifests.
- Activated the existing maintenance task with the explicit `--no-email` flag.
- Updated completion documentation; no payment integration/business logic was changed in this approval phase.

## Remaining limitations

- Payment email is intentionally disabled and requires separate approval. No real email delivery claim is made.
- New external provider testing was prohibited and not performed. Existing provider-verified demo records supplied the actual delivery evidence; separate-recipient and manual financial workflows were tested with isolated fixtures/mocks.
- Browser interaction, responsive visual appearance and PDF visual inspection were not verified in this deployment. The earlier browser access host was unavailable; automated backend/view/PDF regression checks passed.
- Free disk space remains below the existing recurring-backup runner's 1 GiB minimum. That runner/guard was not altered. The small deployment backup used a separate capacity-checked export and its isolated restoration passed; ongoing scheduled backups still require sufficient disk space.
