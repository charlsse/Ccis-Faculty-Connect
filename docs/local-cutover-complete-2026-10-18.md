# FacultyConnect local database cutover completed — October 18, 2026

## Local application status

FacultyConnect is live at **http://127.0.0.1:8000** using:

| Setting | Final value |
| --- | --- |
| Database | `ccis_local_20261018_164245` |
| Host / port | `127.0.0.1:3307` |
| Server | MariaDB 10.4.32, recovery mode 0 |
| Application account | `fc_local_1018@127.0.0.1` |
| Data directory | `C:/Users/Charles/FacultyConnect-Recovery/20261012/restored-data` |
| Authentication guard | `App\Auth\NoRecallerSessionGuard` |
| Session cookie | `facultyconnect_local_20261018_164245` |
| Debug | false |

The final database contains **23 application tables / 281 preserved source records**, plus a newly created sessions table. It has exactly **six original users**, with no disposable test accounts or test audit entries. Existing user credentials, remember tokens, trusted devices, payment records, and collection records were preserved.

Maintenance mode was lifted only after identity, data, privilege, and authentication checks passed. Landing and login pages return 200; unauthenticated dashboard and security-page requests redirect to login. The normal local application is functioning. Cloudflare, ngrok, and Xendit remain disconnected/inactive.

## Maintenance and freshness

Normal Laravel HTTP processes and standalone Apache were stopped before the final snapshot. No queue worker or Laravel scheduler process was found. Windows denied disabling the scheduled task named MySQL Auto Backup; inspection established that it only dumps the unrelated `eco_system.sql` database and is not a FacultyConnect writer. It was left unchanged.

The final original-database reconciliation used a read-only consistent-snapshot transaction. No active non-sleeping client to ccis_connect was observed apart from the inspection connection. All 23 non-session tables remained readable, with **no changes since the preceding controlled test phase**. A fresh single-transaction export excluding historical sessions was restored and checked against that snapshot.

Original rows were rechecked after validation and reopening; their content still matched the final snapshot. No original InnoDB files were deleted, replaced, repaired, or reinitialized. Original XAMPP PID 8408 remains running independently; Laravel no longer points to it.

## Final application record counts

| Table | Verified count |
| --- | ---: |
| account_management_locks | 1 |
| announcements | 1 |
| audit_logs | 180 |
| cache | 31 |
| cache_locks | 0 |
| failed_jobs | 0 |
| job_batches | 0 |
| jobs | 0 |
| migrations | 14 |
| notifications | 4 |
| password_reset_tokens | 1 |
| payments | 0 |
| site_contents | 6 |
| trusted_devices | 18 |
| users | 6 |
| water_collections | 1 |
| water_members | 4 |
| water_notices | 7 |
| water_payment_accounts | 1 |
| water_receipts | 2 |
| water_reference_claims | 1 |
| water_settings | 1 |
| water_submissions | 2 |
| **Total source records** | **281** |

Sessions started empty. The final unauthenticated smoke checks subsequently created four session rows; session counts will naturally change during normal use. None of the 76 historical sessions was imported.

## Backup and preservation evidence

New timestamped protected directory:

`C:/Users/Charles/FacultyConnect-Recovery/20261012/cutover-20261018-164245`

- `final-source.sql`: latest source export, successfully restored into both final and disposable validation databases.
- `snapshot.json` and per-table JSON files: source counts, content hashes, and protected row snapshots.
- `source-backup/`: original `.env`, `config/auth.php`, and `app/Providers/AppServiceProvider.php` before modification.
- `deployed-source/`: private copies of deployed configuration and changed source, including the new guard.
- `build-results.json`, `effective-config.json`, `final-integrity.json`: restore, identity, preservation, and configuration checks.
- `preserved-database-hashes.json`: logical hashes for the earlier baseline and tested candidate.
- `http-results.json`, `supplementary-results.json`, `normal-smoke.json`: successful HTTP tests.
- `artifact-manifest.csv`: SHA-256 inventory for 106 static artifacts. Actively open live logs were deliberately excluded because they are changing.
- Private account files, test cookie jars, and raw data must remain protected and outside the web root.

Final source export SHA-256:

`6af7d5a82e7c6144c55e7d5cac308cea2628f6d805b7c50df5f488154edbfb83`

All **239 original durable physical-backup files** still match their manifest. The recovered session export retains SHA-256 `372e060d9abc261e0bf84cdbf799b749e12ef363e3741c5e4cac451704c3b294`. Existing temporary backups were not removed. The logical content of **ccis_connect_verified** and **ccis_candidate_20261018**, including their existing tables, remains unchanged.

Available disk space at handoff was approximately **10.9 GB**. Backups remain on the same physical drive; a separate protected drive copy is still recommended.

## Configuration and source changes

1. Added `app/Auth/NoRecallerSessionGuard.php`, which disables remembered-login restoration by returning no recaller.
2. Registered the `session-no-recaller` driver in `AppServiceProvider`, retaining Laravel's provider, session store, cookie jar, event dispatcher, request binding, and configured guard options.
3. Changed the web guard driver in `config/auth.php` to `session-no-recaller`.
4. Updated only the necessary `.env` database connection fields and session-cookie setting, preserving APP_KEY and APP_DEBUG=false. The final dedicated account has SELECT/INSERT/UPDATE/DELETE only on the literal final database name; wildcard characters are escaped in its grant. Access to the baseline database was denied in a direct test.
5. Cleared configuration cache. Runtime checks confirmed the actual database, port, account, data directory, recovery mode, guard, cookie, unchanged APP_KEY, and debug=false.
6. Kept normal Laravel session cleanup at **[2,100]**. No test-only cleanup override was carried into normal configuration.

The original session cookie does not address the new session store; the new store contained no old sessions. Legacy remember cookies cannot restore authentication through the integrated guard. Existing trusted-device records and user tokens were not rotated or deleted. Users must sign in again; trusted-device policy after credential validation remains as before.

## Authentication and functionality verification

Write-producing tests used **ccis_cutoverqa_20261018_164245**, a separate fresh disposable database. Its two designated test accounts never entered the final database. The tests used the **normal integrated guard**, not the earlier private prototype.

**56 assertions passed on that copy:**

- 46 assertions covering login forms, invalid passwords, required 2FA, invalid/valid TOTP, recovery-code login, logout, database sessions, expiry, fresh cookies, CSRF, faculty/admin dashboards, security settings, profile, announcements, collections, notifications, privacy pages, role restrictions, and legacy-cookie rejection.
- 10 additional assertions covering contribution pages, collection-report permissions, existing collection detail, supplementary login/logout, and rejection of a cookie addressed to the new guard's own recaller name.

The final normal application then passed **four unauthenticated smoke checks** on port 8000. No real faculty account was used for write-producing tests. All original rows in the disposable copy also remained unchanged; only its test accounts, audit entries, and sessions were affected.

These checks exercise real HTTP requests and database sessions. They do not constitute visual layout review, external email delivery, payment processing, or exhaustive load testing. Those integrations were not activated.

## Integrity and stability

- All final source counts and full-content hashes matched the snapshot.
- Both newly restored databases passed extended table checks, **75 index scans**, and **23 foreign-key checks** with no orphan rows.
- A clean recovered-server shutdown/restart succeeded before local reopening. Final source hashes, the earlier baseline, and the earlier test candidate remained unchanged afterward.
- No new Laravel error markers, MariaDB ERROR/future-LSN/exception markers, or Windows mysqld.exe crash events were found during this cutover phase.
- The final database contains no recovered sessions or disposable users.

The historical October 12 crash remains unexplained, and bounded successful testing is not a guarantee against future runtime faults. Known skip-name-resolve warnings for unused hostname accounts remain. The original damaged database is preserved, not repaired.

## Local operation and restart instructions

The recovered MariaDB process currently serves port **3307**, and Laravel serves **127.0.0.1:8000**. Standalone Apache remains stopped so this handoff exposes only the requested loopback application. No tunnel process is running.

The recovered database is **not installed as an automatic Windows service**. After a reboot, start it using the protected helper from PowerShell:

```powershell
& 'C:\Users\Charles\FacultyConnect-Recovery\20261012\Start-RecoveredMariaDB.ps1'
```

The helper writes a new timestamped runtime log and refuses to launch another server when port 3307 is already occupied. Check the log for successful startup; an occupied port alone does not establish database identity. Starting XAMPP's original MySQL on port 3306 does not start the recovered server.

Then start the local app from the project directory:

```powershell
Set-Location 'C:\xampp\htdocs\CCIS_CONNECT'
& 'C:\xampp\php\php.exe' artisan serve --host=127.0.0.1 --port=8000
```

The existing scheduled backup task does **not** back up this recovered database. This handoff provides verified manual snapshots; a recurring backup policy for the final database remains to be configured separately. Do not use the old original-database configuration as a fallback if startup fails.

## Failure response

If new corruption, crashes, or unexplained errors occur, stop normal requests and workers, retain the recovered database and logs, and enter maintenance mode. Preserve any post-cutover writes before considering another restore. Private source/configuration backups are available for code/config rollback, but keep traffic blocked: **do not automatically reconnect to the known-damaged original database**.

Cloudflare/ngrok reconnection and Xendit activation were not performed. Local recovery is complete; further public exposure or payment testing awaits review.
