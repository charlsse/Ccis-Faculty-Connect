# FacultyConnect controlled recovery and local testing — October 18, 2026

Update: the separately approved final local cutover is complete. See [local cutover completion report](local-cutover-complete-2026-10-18.md). The content below records the preceding test phase.

## Outcome

Controlled local testing succeeded. The candidate preserves **281 current source rows across 23 non-session tables**, including changes since October 12. A new candidate-only sessions table works, and **46 HTTP assertions passed** using two disposable accounts. The original database, normal `.env`, validated recovery baseline, and preserved session export were not replaced or modified by this work.

The isolated HTTP process and MariaDB instance were stopped after testing. Original XAMPP PID 8408 remains independently running and was left untouched. No final cutover occurred. Cloudflare/ngrok were not started, and Xendit work remains paused.

## Freshness reconciliation

The original server identified itself as MariaDB 10.4.32, database `ccis_connect`, port 3306, data directory `C:/xampp/mysql/data/`.

All 23 non-session tables were read in a read-only consistent-snapshot transaction. The resulting private row snapshots were compared against the October 12 validated baseline. A subsequent single-transaction SQL export excluding sessions was restored into the candidate; all restored content hashes matched the read snapshot. A final read-only reconciliation found **no further changes in those 23 tables during testing**.

| Table | October 12 | Current source | Changes |
| --- | ---: | ---: | --- |
| audit_logs | 174 | 180 | Six added rows; no existing rows changed |
| cache | 29 | 31 | Two added entries; no existing rows changed |
| trusted_devices | 17 | 18 | One added row; one existing row changed in revoked_at and updated_at |
| users | 6 | 6 | One existing row changed in two_factor_last_used_step, last_login_at, and updated_at |
| Other 19 tables | 46 total | 46 total | Content hashes unchanged |
| **All 23 tables** | **272** | **281** | Nine added rows; no removed rows |

All payment and water-module records match the prior baseline. The original sessions table was deliberately excluded: recovered historical sessions are preserved separately and must not become active. This phase did not attempt to repair or retest the original corrupted session table.

The export is a verified point-in-time recovery source, not a continuous replica. Since the original server remains running, repeat freshness checks with application writers quiesced immediately before any final cutover.

## Candidate and preservation

- Candidate: **ccis_candidate_20261018**, isolated **127.0.0.1:3307**, MariaDB 10.4.32, recovery mode 0.
- `ccis_connect_verified` remains unchanged: all original content hashes and 75 index scans passed again after testing.
- Dedicated account `fc_candidate@127.0.0.1` has only SELECT/INSERT/UPDATE/DELETE on the literal candidate database name. Database-name wildcard characters are escaped in its grant. A direct attempt to read the baseline schema was denied.
- The fresh sessions table was created only in the candidate from the verified Laravel-compatible schema. It contained zero rows before HTTP requests. No recovered session was imported.
- Original `.env` is byte-identical to its private backup. APP_KEY was not rotated, and existing users' tokens/trusted-device records were not changed.
- All 239 durable physical-backup files still match the preserved manifest. The original recovered-session SQL checksum is unchanged.

New protected artifacts reside at:

`C:/Users/Charles/FacultyConnect-Recovery/20261012/controlled-20261018`

This directory inherits the recovery root's restricted ACL and is outside the web root. It contains sensitive source snapshots, credentials, cookie jars, and private configuration; keep it private.

| Artifact | Purpose |
| --- | --- |
| `original.env.private` | Private backup of unchanged normal application settings |
| `reconciliation.json`, `row-differences.json`, per-table JSON files | Current snapshot, content hashes, and differences |
| `fresh-original.sql` | Successful current non-session export; restore-tested |
| `candidate-pretest.json`, `pretest-integrity.json` | Candidate source validation and empty-session baseline |
| `candidate-bootstrap.php`, `candidate-router.php` | Private isolated Laravel configuration and loopback router |
| `candidate-credentials.json`, `test-accounts.json` | Protected credentials; not included in this report |
| `candidate-http-tests.php`, `http-results.json` | HTTP test harness and 46 assertion outcomes |
| `posttest-integrity.json`, `postrestart-integrity.json`, `final-summary.json` | Source-row preservation and stability results |
| `candidate-after-tests.sql` | Post-test candidate evidence, including disposable data; not a clean cutover source |
| `mariadb.log`, `mariadb-posttest-restart.log`, `http-server.log` | Process diagnostics |
| `artifact-manifest.csv` | SHA-256 inventory of new artifacts at completion |

Fresh export SHA-256:

`fbdd18aad224d21fb7feebc03dc411ab01f4d3316e8df93c910d063e44668d6c`

Post-test candidate export SHA-256:

`72f832cf8fe0d9b8d1128561412a126fb2ea5d6e5835753cce7e39e1811316e5`

Both exports completed successfully. The fresh source export was restore-tested; the post-test evidence dump has not separately been restore-tested. Preparation scripts are one-off audit artifacts and must not be rerun blindly.

## Isolated application and session transition

The test process listened only on **127.0.0.1:8001**. It used private storage, compiled views, cache, logs, and runtime configuration. It did not alter normal `.env` or normal application source. Every boot checked the actual database name, port, data directory, and recovery mode before handling requests.

- APP_DEBUG=false.
- Database sessions used a new cookie: `facultyconnect_recovery_20261018`.
- Cookie HttpOnly and SameSite=Lax were enabled. Secure was false solely for loopback HTTP; public HTTPS configuration needs Secure=true.
- A private `RecoveryNoRememberGuard` extends Laravel's session guard and returns no recaller. This prevents remembered-cookie authentication before user restoration and requires no mutation of real users' remember tokens.
- A valid encrypted legacy remember cookie constructed for a disposable user failed to authenticate.
- Mail used the in-memory array transport; Laravel HTTP-client stray requests were blocked. No external payment or notification send was invoked.
- No queue worker was run. File cache was private to the test process, preserving the copied source cache rows.
- Session garbage collection was disabled for deterministic tests. Restore the normal lottery during final deployment.

This transition exists only in the private test harness. The normal application has not yet received that guard/cookie configuration. Trusted-device behavior remains unchanged; a trusted device does not bypass the initial credential check. No real-user account was used for login testing.

## Authentication, permissions, and navigation results

All 46 HTTP assertions passed:

- Landing page returns 200; unauthenticated dashboard access redirects to login.
- Missing CSRF token is rejected with 419.
- Faculty and admin login forms render; invalid passwords are rejected.
- Correct passwords lead to a 2FA challenge; dashboard access is blocked before 2FA.
- Invalid 2FA codes are rejected; valid TOTP login succeeds for both test roles.
- Both dashboards render, create database sessions, and use the new cookie.
- Profile, security, collections, announcements, notifications, and privacy/security pages return 200 for both roles.
- Faculty receives 403 for members, content manager, and directory; admin receives 200 for those routes.
- Logout redirects and blocks subsequent dashboard access for both roles.
- A recovery code permits the disposable faculty account to sign in.
- Expiry was tested by backdating only that test user's session beyond the configured lifetime; subsequent dashboard access redirects to login.
- A legacy remembered-login cookie cannot restore authentication.

These are real HTTP response/session tests, not visual browser-layout or payment-workflow tests. Role checks exercised the listed routes, not every possible application action. External email and gateway integrations were intentionally not exercised.

## Integrity and intentional test changes

Before HTTP testing, after testing, and after a clean restart:

- All **281 source rows** were present with unchanged column values.
- All 23 source tables passed extended checks and **75 index scans**.
- All **23 declared foreign-key relationships** had zero orphan rows.
- Candidate account access to the baseline remained denied.
- Sessions passed an extended table check after testing.

Intentional candidate-only additions were two disposable users and seven test audit entries. Eight test sessions remained as evidence; **zero had an authenticated user_id** at the final session check. Test-account security state changed through login, logout, and recovery-code testing. No existing source user, payment, collection, trusted-device, or other source row changed. The source server's 23-table snapshot also remained unchanged at the final reconciliation.

Do not promote this test database directly: it contains disposable users and test history. Build the final clean candidate from the latest verified source export and a fresh empty sessions table instead of deleting test rows from the tested copy.

## Stability and remaining concerns

No error 1030/1877, future-LSN mismatch, unexplained HTTP 500, or MariaDB crash occurred during this phase. No error-level entries were found in the private Laravel log inspection. Windows Application event review found no mysqld.exe crash since this phase's startup. MariaDB logs confirm both controlled shutdowns, and post-write restart verification passed.

The earlier October 12 crash remains unexplained; successful bounded tests do not establish indefinite production stability. Known skip-name-resolve warnings about unused hostname accounts remain. The original damaged database has not been repaired. The same-drive archives do not protect against physical drive failure.

No public-tunnel process was found. This is not an audit of remote tunnel configuration. The original XAMPP server remains running and may acquire newer data later; freshness must be reconfirmed before cutover.

## Proposed final local cutover — separate approval required

1. Agree a local maintenance window and quiesce normal HTTP/background writers. Keep tunnels disconnected. Do not repair or replace original InnoDB files.
2. Repeat read-only freshness reconciliation and create a new, uniquely named protected export. If current rows are unreadable or a dangerous error recurs, stop. Restore and validate a **new clean final database** on the isolated instance; retain both the validated baseline and this disposable test candidate.
3. Create the empty Laravel sessions table in that final database; do not import old sessions or disposable users. Verify counts, content, indexes, and foreign keys. Give the normal app a dedicated account scoped to that final database.
4. Preserve private configuration/source backups and implement the tested no-recaller guard in normal application code. Select a fresh session-cookie name; retain APP_KEY and existing trusted-device/remember-token records. Restore normal session cleanup settings. This guard transition must be active before any normal application request can reach the recovered database.
5. With normal requests still blocked, update only approved database settings and session-cookie configuration, keep APP_DEBUG=false, clear cached configuration, and verify the effective database identity using the app's new scoped credentials. Do not expose diagnostic credentials or debug pages.
6. Run a short approved local smoke test. Use designated test accounts only in a disposable validation copy or explicitly authorize their creation in the final database; do not silently write to real faculty accounts. Enable normal local access only after those checks pass.
7. Monitor application/database logs and preserve the original and all recovery artifacts. Cloudflare/ngrok reconnection and Xendit work require a later decision after local stability and security review.

## Rollback

If startup, validation, or authentication fails, stop requests and workers and preserve the candidate state and logs. Restore the privately backed-up application code/configuration while keeping normal traffic blocked. **Do not automatically point traffic back at the known-damaged original.** Return to the isolated validated baseline for investigation, reconcile any new writes before another attempt, and obtain approval before discarding or replacing any candidate.

Current status: ready for review of a final local cutover, with no cutover performed or implied by these results.
