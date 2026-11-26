# FacultyConnect final isolated validation — October 18, 2026

Update: the subsequently approved reconciliation and local application tests are complete. See [controlled recovery and testing results](controlled-recovery-testing-2026-10-18.md). No final cutover has occurred.

## Decision

The recovered database passes isolated integrity checks and is **suitable for controlled local Laravel testing after approval**, with two qualifications: the historical MariaDB crash remains unexplained, and the original XAMPP server is now independently running, so the October 12 snapshot must not be assumed current for cutover.

No database cutover, active session-table creation, authentication testing, tunnel activation, or payment integration was performed. Laravel configuration remains unchanged. The isolated server was stopped after testing.

## Recovered session export

The existing protected `salvaged-sessions.sql` was imported into **session_export_validation.sessions** on the isolated instance at **127.0.0.1:3307**. Neither the original database nor `ccis_connect_verified` received a sessions table.

- Import succeeded, exit code 0.
- All **76 rows** match the content hash from the three original salvage scans.
- `SHOW CREATE TABLE` matches the CREATE TABLE definition in the export after whitespace normalization.
- `CHECK TABLE EXTENDED` reports OK.
- All **three indexes** were scanned successfully, with 76 results per scan.
- Full reads and content hashes remained valid across subsequent restarts.
- Recovered session contents were not printed or activated. Their original export and JSON remain protected and unchanged.

Session content hash:

`8387443f3b852c9be58ce8d77a3de53592b0476b2a343a4a6960a0da3a4ea595`

This establishes recovery of those 76 exported rows. It does not prove that they represent every pre-corruption session or that any should be accepted for authentication.

## Application data and stability

Three controlled isolated-server startup/read/shutdown cycles were completed, with three validation passes per cycle: **nine passes total**. The first cycle occurred October 12 and the final two October 18. This was a bounded read/integrity test, not a six-day continuous uptime test or concurrent application load test.

Every pass verified:

| Check | Result |
| --- | --- |
| Exact application table set | 23 tables, unchanged |
| Per-table counts | All match the documented baseline; 272 total rows |
| Full application row-content hashes | All match the prior validation baseline |
| Application extended table checks | All 23 OK |
| Application index scans | All 75 passed |
| Declared foreign-key relationships | 23 checked; zero orphan rows |
| Session test rows / hash / schema | 76; all match |
| Session extended check / index scans | OK; all three passed |

Each cycle log confirms shutdown completion. None contains an ERROR, exception, future-LSN, or crash-recovery entry. No new MariaDB application-crash events were found during this phase. The known ignored hostname-account warnings under `skip-name-resolve` remain; they do not invalidate the data checks.

The isolated instance used MariaDB 10.4.32, the explicit `restored-data` directory, localhost binding, port 3307, and **innodb_force_recovery=0**. No additional force-recovery run was needed in this phase.

## Crash investigation and correction to earlier reporting

Windows Application event 1000 confirms three historical mysqld.exe crashes on October 12 at host-recorded times 10:15:15, 12:20:12, and 12:24:52. All have exception **0x80000003**, module mysqld.exe 10.4.32, and offset **0x643842**. The 12:24:52 event corresponds to the initial isolated restore failure. The 12:20:12 event identifies PID **12356**, the original server process, around the earlier shutdown request.

**Correction:** the original shutdown command returned success and process exit was confirmed before its physical backup, but the newly reviewed Windows event means the original shutdown cannot be described as confirmed clean. The offline backup remains a verified copy of the stopped, damaged database; it is not a proven clean or healthy physical backup. Earlier hash and successful logical-restore results remain valid.

The isolated failure was not reproduced by the session import or the nine validation passes. Its console-free log ended without a usable assertion stack. No matching debugger/symbol setup was found in the inspected locations. The available exception code/offset alone does not establish a specific engine bug, storage fault, or configuration cause. The change to an explicit working directory and console logging coincided with success but is not proof of causation. MariaDB's [10.4.32 release notes](https://mariadb.com/docs/release-notes/community-server/old-releases/10.4/10.4.32) do not establish a match to this local failure.

Reviewed Windows System error events from October 12 onward contained **zero matching disk/NTFS/storage/volume error events**. Available C: space at final verification was **12,341,682,176 bytes**. These checks do not substitute for a hardware health test or rule out all storage problems.

No runtime configuration or original files were changed to speculate about a fix. If another crash occurs during approved testing, stop testing, preserve fresh console logs and the Windows crash dump, and obtain a symbolized stack before choosing a runtime remedy.

## Preservation and current environment

Protected recovery root remains:

`C:/Users/Charles/FacultyConnect-Recovery/20261012`

The new audit artifacts are under **final-validation/**:

- `preservation-baseline.csv` and `preservation-final.csv`: all **249 checked artifacts** matched, including the durable archive, recovered session artifacts, and `.env`.
- `restore-results.json`, `cycle-2-results.json`, `cycle-3-results.json`: nine successful validation passes.
- `cycle-1.log`, `cycle-2.log`, `cycle-3.log`, `cycle-log-summary.json`: isolated startup/shutdown evidence.
- `session-import.stdout`, `session-import.stderr`: import diagnostics.
- `windows-mariadb-events.json`, `windows-crashes-final.json`: crash evidence.
- `validate.php`: validation script retained for audit; its restore mode creates a new schema and must not be blindly rerun.

Original backups and existing artifacts were not overwritten or deleted. Existing temporary backups were not used or changed in this phase. The original directory was not started, stopped, repaired, or written by this phase's commands.

On resumption October 18, XAMPP PID **8408** was already running with `C:/xampp/mysql/bin/my.ini`. It was left untouched. Consequently this phase does **not** claim that live original files remain byte-identical to the October 12 snapshot. Its current records were not queried or reconciled. Do not interpret the restored October 12 counts as proof of current original counts.

No cloudflared or ngrok process was present at the final check. Remote tunnel settings were not independently audited. Laravel still has APP_DEBUG=false, DB_PORT=3306, DB_DATABASE=ccis_connect, and SESSION_DRIVER=database; `.env` hash matches the phase baseline.

## Safest proposed transition — approval required

1. **Confirm source freshness before any cutover.** Determine whether users or processes have written to the original since October 12. With approval, quiesce application writers and perform read-only reconciliation. Preserve a fresh export/snapshot as appropriate; reconcile newer records in a separate candidate. Never overwrite the original with this older snapshot.
2. Preserve the verified recovery instance as a baseline and prepare a separate candidate/test database from verified exports. Give Laravel a dedicated local account scoped to that candidate, not root. Keep the original database and protected archives unchanged.
3. In the candidate only, **create a new empty sessions table** from the verified schema after explicit approval. Do not import any recovered sessions. Leave all 76 recovered rows in the protected archive and isolated investigation schema. Do not run migrate:fresh/reset or a broad migration batch.
4. Use a new session-cookie name for the transition, retain APP_KEY so existing encrypted application data remains readable, and explicitly prevent legacy remembered-login cookies from restoring authentication. Current login code uses `Auth::login($user, false)`, but that alone is not proof that older remember cookies can never be accepted by Laravel's guard. Review a guard/cookie transition before implementation; any proposal to rotate stored remember tokens or revoke trusted-device records requires separate authorization because it changes user/security records. Trusted-device records currently bypass the second-factor step only after credential validation; retain them unless a separate policy change is approved.
5. Back up the connection configuration privately, then switch only the approved local test process to the candidate on port 3307, keeping APP_DEBUG=false and tunnels disconnected. Clear its configuration cache and verify its actual database identity before any request.
6. Perform approved authentication/session, logout, faculty-dashboard, and administrator-dashboard tests using designated test accounts. Login code updates user timestamps/status and audit logs, so real faculty accounts must not be used under the present no-record-modification restriction. Explicit authorization for test-account setup or a disposable candidate is needed.
7. Check database and application logs, counts, and intended test effects. If an unexplained crash recurs, stop testing and preserve evidence. For rollback, stop the test application and revert its connection configuration; do not silently resume use of the known-damaged original database.

The next approval should cover the freshness reconciliation, candidate preparation and empty-session-table policy, and local authentication-test scope. **No cutover or authentication test is authorized or performed by this report.** Cloudflare/ngrok and Xendit remain out of scope until local validation is complete.
