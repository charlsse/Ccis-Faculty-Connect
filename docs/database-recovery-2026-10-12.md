# FacultyConnect database diagnosis — 2026-10-12

## Outcome
Correction from subsequent Windows-event review: the original process crashed around its shutdown request; shutdown was not confirmed clean. The backup was taken after process exit. See [final validation report](final-isolated-validation-2026-10-18.md).

Update: the subsequently authorized isolated recovery and its validation results are documented in [isolated-recovery-results-2026-10-12.md](isolated-recovery-results-2026-10-12.md). The text below preserves the initial diagnosis and pre-approval state.

Database recovery is pending approval. No tables or InnoDB files were deleted, replaced, rebuilt, or repaired. No application records were intentionally modified. MySQL was shut down using mysqladmin (exit 0), and process exit was confirmed before copying files. Leave Cloudflare disconnected.

## Established findings
- Laravel connects to the original ccis_connect database on 127.0.0.1:3306.
- Server: MariaDB 10.4.32; data directory C:/xampp/mysql/data; innodb_force_recovery=0.
- sessions COUNT fails with error 1712 (corrupted index); its export fails at row 0 with error 1030/1877.
- mysql_error.log at 2026-10-12 11:49:01 reports page LSN 11404527 in system tablespace 0 versus current system LSN 5995184. Other implicated spaces include 350, 360, 362, 365, and 367. This establishes a broader tablespace/redo inconsistency, not merely a Laravel configuration issue. The initiating event (file replacement, lost redo, or other corruption) is not established.
- Repeated startup/crash-recovery entries appear at 11:48:57 and 11:49:00. They are evidence of unclean shutdown/recovery attempts, not proof of their cause.
- Approximately 14.4 GB disk space was available. Directory ACL grants Authenticated Users Modify; no permission-denied storage error was observed. Service-account-specific permissions were not independently verified.
- No cloudflared process was found. This does not independently verify remote tunnel configuration.

## Readability baseline
23 tables passed COUNT and were included in a successful logical dump:
account_management_locks=1, announcements=1, audit_logs=174, cache=29, cache_locks=0, failed_jobs=0, job_batches=0, jobs=0, migrations=14, notifications=4, password_reset_tokens=1, payments=0, site_contents=6, trusted_devices=17, users=6, water_collections=1, water_members=4, water_notices=7, water_payment_accounts=1, water_receipts=2, water_reference_claims=1, water_settings=1, water_submissions=2.

These reads and successful export establish readability, not complete logical/index integrity or historical completeness. In particular, payments=0 is the observed baseline, not proof that no payments ever existed. Session count could not be obtained.

## Changes
- Actual .env APP_DEBUG changed from true to false.
- php artisan config:clear succeeded; Laravel bootstrap confirmed debug=false.
- SESSION_DRIVER remains database because broader InnoDB errors make a session-only workaround insufficient.
- MySQL is stopped after offline preservation. It has not been restarted.

## Backup status
New backup directory (outside the web root):
C:/Users/Charles/AppData/Local/Temp/FacultyConnect-recovery-20261012-121955

- full.sql: FAILED, exit 3, 61099 bytes; incomplete and unsuitable as a complete restore source.
- excluding-sessions.sql: exit 0, 101923 bytes, 23 CREATE TABLE statements, completion marker present. SHA-256: 612b67c44250807c1bdef0b0fb826548d65d69f96ce2c8d75bd9d0bc6a405c18. Not yet restore-tested.
- physical-data: all 239 copied files verified against the stopped source using SHA-256; physical-manifest.csv records hashes and sizes. my.ini copied separately. This preserves the current damaged state, not a proven healthy backup. Shutdown command succeeded and process exited, although the inspected log did not show a fresh shutdown-complete line.
- Temporary storage is not durable archival storage; retain a protected durable copy before recovery work.
- Existing SQL backups dated September 6 and 13 contain completion markers but lack water-module tables; not restore-tested and insufficient for all current data.
- C:/xampp/mysql/data_old2 contains ccis_connect and September 7 InnoDB files; consistency and completeness unverified. data_old and data_old1 lack this database. XAMPP backup is not an established current application backup.

## Verification limitations
Laravel bootstrap and database connectivity passed before shutdown. Full export reproduced the original failure. Login/logout, faculty/admin dashboards, and session behavior were not exercised against the damaged database: they can write sessions/audit/account state and the database is now stopped. Normal HTTP functionality and record preservation after recovery are not yet verified. Application must not be exposed through Cloudflare yet.

## Proposed recovery requiring approval
1. Retain the original data directory and verified offline copy untouched; put another copy in protected durable storage.
2. Build an isolated MariaDB 10.4.32 recovery instance on a different local port and directory; do not initialize or overwrite the original directory.
3. Restore excluding-sessions.sql there. Verify all 23 table counts, constraints and representative full reads against the baseline.
4. Attempt session salvage only on an expendable copy of the offline backup. Any force-recovery use requires a separate controlled procedure; never enable it on the original.
5. If session salvage is impossible, obtain explicit approval to create an empty sessions table in the replacement database. Existing logins would be invalidated; original session files remain preserved.
6. Only after validation and approval, switch Laravel to the recovered instance, test authentication with an authorized test account, and test both dashboard roles without real payment changes. Keep debug=false. Reconnect Cloudflare only after local verification succeeds.

MariaDB reference: https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-redo-log
Recovery modes do not repair corruption: https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-troubleshooting/innodb-recovery-modes
