# FacultyConnect isolated recovery results — October 12, 2026

Update: see [final isolated validation, October 18](final-isolated-validation-2026-10-18.md) for successful session restore testing, additional restart checks, and corrected evidence about the original shutdown. This report records the earlier phase.

## Result and current state

The successful export was restored into **ccis_connect_verified** on an isolated MariaDB **10.4.32** instance configured for **127.0.0.1:3307**. All 23 exported tables and 272 rows passed the checks below. Separately, **76 session rows were salvaged and exported** from an expendable physical copy using recovery mode 1 on port 3308.

The original database has not been repaired or replaced. All 239 original files still match the saved SHA-256 manifest. Both isolated servers were shut down after testing; no mysqld or cloudflared process was present at the final check. Laravel still points to its original database; no authentication tests or payment integration work were performed. No empty sessions table was created.

## Protected backup locations

Durable recovery root:

`C:/Users/Charles/FacultyConnect-Recovery/20261012`

Inheritance is disabled on this directory. Access is granted only to Charles, SYSTEM, and Administrators. It is outside the XAMPP web root and temporary directory. These copies are on the same C: drive, so they protect against accidental recovery changes, not failure of that drive.

| Artifact beneath recovery root | Status |
| --- | --- |
| `archive/physical-data` | 239 physical files verified against the original manifest |
| `archive/physical-manifest.csv` | Relative paths, file sizes, and SHA-256 hashes |
| `archive/excluding-sessions.sql` | Successful source export; 101,923 bytes; now restore-tested |
| `archive/my.ini` | Original configuration preserved as metadata; not used to start recovery instances |
| `archive/full.sql` | Prior incomplete dump retained as evidence, not used for restoration |
| `restored-data` | Isolated restored instance, now stopped |
| `restored.ini` | Explicit isolated data directory, localhost binding, port 3307 |
| `validation.json` | Table counts, extended-check results, index definitions, row-content hashes, and foreign-key results |
| `session-salvage-data` | Expendable physical copy; never used as Laravel's database |
| `session-salvage.ini` | Explicit copy-only storage paths, localhost port 3308, read_only=ON, innodb_force_recovery=1 |
| `salvaged-sessions.sql` | Successful session export; 39,736 bytes; not yet restore-tested |
| `salvaged-session-rows.json` | Sensitive recovered session content; keep protected |
| `session-results.json` | Three scan results, matching content hashes, export exit code/hash |
| `final-preservation-check.json` | Final original/backup preservation and storage checks |

Local recovery credentials are stored only in the protected recovery directory. Do not publish that directory or its contents. Audit scripts are retained there for review; the restoration script is a one-off script, not safe to rerun blindly.

The original temporary backup remains intact at:

`C:/Users/Charles/AppData/Local/Temp/FacultyConnect-recovery-20261012-121955`

Before preparation, available space was **14,269,890,560 bytes**; after completion it was **13,878,255,616 bytes**. All copied artifacts were hash-compared, and the 239 physical files in the original directory, temporary backup, and durable archive were reverified against the saved manifest after testing.

Source SQL SHA-256:

`612b67c44250807c1bdef0b0fb826548d65d69f96ce2c8d75bd9d0bc6a405c18`

Recovered session SQL SHA-256:

`372e060d9abc261e0bf84cdbf799b749e12ef363e3741c5e4cac451704c3b294`

## Restored table counts

Every count matches the documented pre-recovery baseline. Every table also passed a full SELECT and CHECK TABLE EXTENDED.

| Table | Baseline | Restored |
| --- | ---: | ---: |
| account_management_locks | 1 | 1 |
| announcements | 1 | 1 |
| audit_logs | 174 | 174 |
| cache | 29 | 29 |
| cache_locks | 0 | 0 |
| failed_jobs | 0 | 0 |
| job_batches | 0 | 0 |
| jobs | 0 | 0 |
| migrations | 14 | 14 |
| notifications | 4 | 4 |
| password_reset_tokens | 1 | 1 |
| payments | 0 | 0 |
| site_contents | 6 | 6 |
| trusted_devices | 17 | 17 |
| users | 6 | 6 |
| water_collections | 1 | 1 |
| water_members | 4 | 4 |
| water_notices | 7 | 7 |
| water_payment_accounts | 1 | 1 |
| water_receipts | 2 | 2 |
| water_reference_claims | 1 | 1 |
| water_settings | 1 | 1 |
| water_submissions | 2 | 2 |
| **Total** | **272** | **272** |

## Integrity validation

- Exact expected set of 23 tables was present; sessions was absent from this restored schema as expected.
- Import finished with exit code 0 on the successful attempt.
- Full-table reads succeeded, including all columns of user, audit, trusted-device, and water-module records. Values were not printed or edited.
- CHECK TABLE EXTENDED reported OK for all 23 tables.
- All **23 declared foreign-key constraints** were enumerated. Explicit anti-join checks found **zero orphan records**, including correct handling of nullable/composite keys. Foreign-key checks were enabled in the validation session.
- **75 index scans** completed and returned their tables' expected row counts. Index definitions are retained in validation.json.
- Row-content hashes were computed for all tables, then compared after a clean shutdown/restart; every hash and count matched. All 75 index scans passed after restart as well.
- Final isolated-server log confirms normal shutdown and shutdown completion. No 1030/1877 or future-LSN error was observed during the successful restore, validation, or clean-restart verification.

These checks establish successful recovery of the exported data, not historical completeness or business-level correctness. The observed payments count was already zero before recovery. No pre-corruption session count exists for comparison, and application-level relationships that are not declared foreign keys were not exhaustively audited. Content hashes establish stability of restored rows across restart; they are not pre-corruption hashes.

## Session recovery

The disposable physical copy contained no detected reparse points or `.isl` external tablespace references before startup. Its configuration explicitly redirects data, system tablespace, and redo locations into the copy. It never references the original data directory.

With recovery mode 1 on that copy:

| Read method | Rows | Result |
| --- | ---: | --- |
| Full SELECT | 76 | Success |
| Primary index ascending | 76 | Success |
| Primary index descending | 76 | Success |

All three scans produced the same order-independent content hash:

`8387443f3b852c9be58ce8d77a3de53592b0476b2a343a4a6960a0da3a4ea595`

The subsequent mysqldump exited 0 and its SQL file contains a completion marker. The session export has not been imported into the validated database or tested through Laravel. Recovery mode can make damaged data readable; it does not repair the physical corruption or prove that all historical rows survived. See [MariaDB recovery-mode documentation](https://mariadb.com/docs/server/server-usage/storage-engines/innodb/innodb-troubleshooting/innodb-recovery-modes).

## Unexpected events and limitations

1. Initial isolated account setup returned error 1396 when attempting to alter a hostname-based root account. Local loopback accounts were secured successfully. The hostname account is ignored under skip-name-resolve; startup logs retain that warning.
2. The first import into isolated schema ccis_connect failed at line 79 while inserting announcements, with client error 2013. Windows recorded an APPCRASH for mysqld.exe 10.4.32, exception 80000003, offset 0000000000643842. The cause is not established. That partial schema was preserved and is not the validated schema.
3. After restarting only the isolated server with captured console diagnostics and the MariaDB bin directory as its working directory, import into the new ccis_connect_verified schema succeeded. This sequence does not prove that the working-directory change fixed the crash. Successful restore and clean-restart checks did not reproduce it; further local stability testing is recommended before any cutover.
4. One content comparison initially used a different connection charset and reported a water_collections mismatch. Repeating it with the same utf8mb4 connection charset used for initial validation passed. No records were changed to resolve this comparison issue.
5. The original remains physically inconsistent. The successful replacement schema and session export do not repair original InnoDB files.

## Recommended next steps — no cutover authorized

1. Retain all archives and evidence. A second protected copy on a separate physical drive would add hardware-failure protection.
2. Restore-test the recovered 76-session export in another isolated schema and validate its rows, schema, and indexes. No empty replacement is presently needed.
3. Investigate the initial MariaDB process crash and run additional isolated stability checks before considering this runtime ready for application use.
4. Review whether old sessions should be retained for active authentication; recovery does not establish whether they are current or expired. Any decision to replace them with an empty table still requires explicit approval.
5. Obtain approval before changing Laravel's database connection or running application authentication tests. Then test authorized accounts, logout, both dashboard roles, and logs locally, without real payment changes.
6. Keep APP_DEBUG=false and Cloudflare disconnected. Do not resume Xendit work until database and local application verification are complete.
