# Recovered FacultyConnect database backups

Configured October 18, 2026. This process backs up **ccis_local_20261018_164245 on 127.0.0.1:3307**, never the damaged original on port 3306.

## Schedule and storage

Windows task: **FacultyConnect Recovered Database Backup**. Runs at 08:00 and 16:00 Asia/Manila and when Charles signs in. Missed starts run when available. Failures retry three times at five-minute intervals. Overlapping runs are ignored; the runner also uses a file lock. Battery operation is allowed; execution is limited to 30 minutes.

The backup task runs under Charles's interactive Windows account without storing a Windows password. **Charles must be signed in and recovered MariaDB must be running.** It does not start MariaDB, operate while Windows is off, or provide unattended server-service coverage. The old MySQL Auto Backup task is unrelated and unchanged.

As of November 18, 2026, a separate **FacultyConnect Recovered Database Availability** task runs the existing protected `C:/Users/Charles/FacultyConnect-Recovery/20261012/Start-RecoveredMariaDB.ps1` helper at Charles's sign-in and every minute while signed in. The helper starts the configured recovered instance only when loopback port 3307 is closed; it leaves an already listening instance alone. This fixes the recurring homepage HTTP 500 caused by the database not starting after reboot. It never switches the application to the damaged original database on port 3306. The task was exercised with LastTaskResult 0; homepage and login both returned HTTP 200. Laravel now returns a friendly HTTP 503 with Retry-After 60 for database connection outages. This task does not start the PHP web server or provide service coverage before sign-in.

| Item | Location |
| --- | --- |
| Backup runner | `C:/xampp/htdocs/CCIS_CONNECT/tools/backup-recovered-database.php` |
| Private configuration | `C:/Users/Charles/FacultyConnect-Recovery/backup.env` |
| SQL, JSON manifests, run history | `C:/Users/Charles/FacultyConnect-Recovery/recurring-backups` |
| Exported task definition | `recurring-backups/scheduled-task.xml` |
| Restore validation evidence | `recurring-backups/restore-verification-20261018-090240.json` |

The private configuration and output directory restrict access to Charles, SYSTEM, and Administrators. The backup account has database-scoped SELECT, SHOW VIEW, TRIGGER, and EVENT privileges; it cannot read the original database or other recovery schemas. Credentials are read from the protected environment file and passed to the dump child through its environment, never command arguments or reports. Do not print that environment file or publish SQL backups.

Each run checks the actual database name, port, recovery mode, InnoDB table engines, and at least 1 GiB free space. It uses a single-transaction logical dump, validates exit status, completion marker, and table coverage, then finalizes a unique timestamped SQL file with size and SHA-256 metadata. Failed partial exports remain marked `.partial.sql` and must not be restored as complete backups. `runs.jsonl` contains sanitized success/failure records. There is **no automatic deletion** or archive rotation. Monitor disk space and arrange a protected copy on another physical drive.

The current schema has no stored routines. The dump includes tables, rows, triggers and events; adding stored routines, non-InnoDB tables, concurrent schema changes, or changing the database name requires a backup review. Logical backups do not include uploaded proof files, application code, APP_KEY, or Windows/MariaDB accounts. Preserve these separately; the existing protected recovery configuration archives must remain available for encrypted fields to be readable.

## Verification performed

The scheduled task was exercised and returned **LastTaskResult=0**. The next scheduled run at verification was October 19 at 08:00.

First backup: `20261018-085724-510024a9.sql`, 106,450 bytes, 24 tables, SHA-256:

`2c5315e6f9c12820e4f37772591f7f65f27521c0cc6e0b3e2010f7324dea559a`

It was restored successfully into the new isolated schema **fc_backup_verify_20261018_090239**, on port 3307. All 24 extended table checks passed. The 23 non-session tables contained **281 records**, and all their full-content hashes matched the active recovered database. The restored sessions remain unused in this verification schema. This schema was retained as evidence, not connected to Laravel.

The task-generated backup was `20261018-090241-ab5bc690.sql`, SHA-256 `9fad340cfd9807cc135d0cae2947d4ce92e0c4a59e36f58726effd580f42b304`. A further run of the finalized runner produced `20261018-090942-3a07f6bb.sql`, SHA-256 `24acd9ba4cef16cc41b8a6375b6fbfcdc1416ac361b87a9da4b1024060bef46a`. Those later exports passed completion/hash checks but were not separately restored. Session rows changed normally during unauthenticated local smoke checks.

## Routine checks

From PowerShell under Charles's account:

```powershell
Get-ScheduledTaskInfo -TaskName 'FacultyConnect Recovered Database Backup' |
    Format-List LastRunTime,LastTaskResult,NextRunTime
Get-Content 'C:\Users\Charles\FacultyConnect-Recovery\recurring-backups\runs.jsonl' -Tail 3
```

Confirm a recent success, not only task registration. A nonzero task result or stale success needs investigation. A stopped database never causes fallback to port 3306. To run a backup on demand:

```powershell
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\CCIS_CONNECT\tools\backup-recovered-database.php' 'C:\Users\Charles\FacultyConnect-Recovery\backup.env'
```

## Restore procedure — always into a new schema first

1. Keep requests/workers stopped during any actual recovery decision. Preserve the current database and logs, including any writes newer than the chosen backup. Never overwrite either existing recovered or original schemas.
2. Choose a finalized `.sql` and its matching JSON manifest. Compare `Get-FileHash -Algorithm SHA256 -LiteralPath '<chosen SQL path>'` with the manifest. Check completion status and free storage. Use the same MariaDB 10.4.32-compatible isolated instance, port 3307, recovery mode zero.
3. Obtain an authorized restore account privately; the read-only backup account deliberately cannot create a database. Open the client with a password prompt, never a password argument:

   ```powershell
   & 'C:\xampp\mysql\bin\mysql.exe' --no-defaults --host=127.0.0.1 --port=3307 --user=AUTHORIZED_RESTORE_ACCOUNT --password --default-character-set=utf8mb4
   ```

4. Verify `SELECT @@port, @@datadir, @@innodb_force_recovery;`. Then create a **new, unused** verification name. Substitute a fresh timestamp in the example:

   ```sql
   CREATE DATABASE fc_restorecheck_YYYYMMDD_HHMMSS CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE fc_restorecheck_YYYYMMDD_HHMMSS;
   SELECT DATABASE();
   SOURCE C:/Users/Charles/FacultyConnect-Recovery/recurring-backups/CHOSEN_FINALIZED_FILE.sql;
   ```

   These exports were created without `--databases` and do not select the original schema. Do not use an arbitrary dump containing a different `USE`/`CREATE DATABASE` statement. If import returns any error, stop and retain the new partial schema for diagnosis.
5. Confirm the expected table set, counts, full reads, indexes, declared foreign-key relationships and `CHECK TABLE ... EXTENDED` results. Compare available snapshot hashes and inspect MariaDB logs. The protected first-restore verifier records the verification method; it is a one-off audit script, not a general restore command.
6. Keep the restored schema disconnected from normal Laravel until validated and separately approved for cutover. Preserve APP_KEY for existing encrypted data. Review session invalidation before activation; do not silently reactivate backed-up login sessions. Create/regrant an appropriate scoped application account separately. A logical application dump does not restore server accounts.

Repeat a restore drill periodically and after schema changes. Backups on this C: drive cannot protect against physical failure of that same drive.
