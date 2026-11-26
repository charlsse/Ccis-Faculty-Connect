# Windows background task repair — November 19, 2026

All times below are Asia/Manila. This repair changes how existing commands are started; it does not disable essential maintenance or change application authentication, database connections or payment behavior.

## Approved follow-up — 01:06

**Final verification at 01:13:** after the user reported successfully running the administrator command, the legacy **MySQL Auto Backup** task was independently confirmed **disabled**. Namespace-aware XML comparison confirmed only its Enabled setting changed; its definition and all archive files remain preserved. Payment Synchronization, Presentation Support and Database Availability remain enabled with LastTaskResult **0**, and the recovered backup remains enabled using the quiet `backup` launcher action. Its last result is correctly **1** for insufficient storage. Both approved backup-task changes are now complete; the earlier administrator blocker below is resolved. Protected evidence: `task-runtime\legacy-disable-verified.json`.

The user confirmed **“No pop-ups during three minutes”**, providing the requested desktop visual confirmation in addition to process telemetry. The user also approved both proposed backup-task changes.

**Recovered backup quiet action completed.** Only its Action now invokes `FacultyConnectTask.exe backup`; original user, schedule (08:00/16:00/sign-in), permissions, settings and safeguards are unchanged, verified by XML comparison excluding Actions. The scheduler-driven test at **01:06:02** started without a console attachment and correctly returned **1**, with the real storage validation failure captured in its protected stderr log. Failures are not hidden. Payment, presentation and database maintenance tasks still report 0. No backup/archive file was deleted or moved.

**Legacy disable remains blocked by Windows administrator permissions.** `Disable-ScheduledTask -TaskName 'MySQL Auto Backup'` returned access denied (0x80070005), and the legacy task remains enabled. Its original XML was preserved. No elevation window was launched. The user has been asked to run that one approved command in Administrator PowerShell; its disabled state must be checked afterwards. This is an operating-system permission failure, not an approval-review rejection.

Latest free space is about **490 MiB**, still roughly **534 MiB short of the 1 GiB minimum**. No fresh successful export or isolated restoration is claimed. Protected follow-up evidence is `task-runtime\backup-task-followup.json`, the approved-before/after task XML and the backup run/output logs. Earlier pending-approval statements below describe the initial repair snapshot and are superseded by this follow-up.

## Confirmed sources

The primary source was **FacultyConnect Payment Synchronization** running the console-subsystem `C:\xampp\php\php.exe` in Charles's interactive desktop session. Process/console telemetry established the following, rather than relying only on its one-minute frequency:

- At **00:39:20**, scheduled maintenance PHP PID **32664** was followed by CMD PID **30820**, whose recorded parent was **32664**. Windows reported the CMD console window handle **4656446** as visible.
- At **00:39:56**, scheduled maintenance PHP PID **29700**, parent **2656** (the task execution context), had visible console handle **4787518**.
- At **00:44:27**, Presentation Support PowerShell PID **32388**, parent **2656**, had visible console handle **3345810**, despite its `-WindowStyle Hidden` argument.

The installed PHP binary reports version 8.2.12 and PE subsystem **3 (Windows console)**. Symfony's installed `packages/symfony/console/Terminal.php` probes `stty` / `mode CON` through shell/process calls when terminal dimensions are unavailable. This explains an additional CMD child from maintenance output formatting. Supplying unattended terminal dimensions avoids that branch without editing framework code.

The PowerShell presentation and database monitors use the same console-based PowerShell startup pattern. Presentation's visible console was directly captured; Database Availability's sampled windows were not visible, but its matching console startup mechanism was also removed to prevent startup flashes. No XAMPP control-panel process was identified as a separate recurring source.

Task Scheduler Operational history was disabled, so historical task-event correlation was unavailable. A WMI process-start subscription was unavailable too. A bounded GUI-subsystem diagnostic used process snapshots, parent PIDs, task launch times and existing-console/visibility metadata instead. This is process/window telemetry, **not an agent visual observation of the desktop**.

## Implemented changes

Only the Actions of these three existing one-minute tasks changed:

| Task | New action argument |
| --- | --- |
| FacultyConnect Payment Synchronization | `maintenance` |
| FacultyConnect Presentation Support | `presentation` |
| FacultyConnect Recovered Database Availability | `database` |

All three now execute **`C:\xampp\htdocs\CCIS_CONNECT\tools\FacultyConnectTask.exe`**, a reviewed .NET Framework Windows-GUI-subsystem launcher. It creates the original PHP/PowerShell process with `UseShellExecute=false`, `CreateNoWindow=true`, closed input and redirected output/error. It presents no form, console or interactive prompt. No alternate PHP executable was selected: the existing php.exe remains the maintenance engine. php-win.exe exists but was not substituted.

The launcher accepts only fixed job names with fixed commands. Maintenance still runs:

`C:\xampp\php\php.exe "C:\xampp\htdocs\CCIS_CONNECT\artisan" payments:maintain --no-email`

Its private child environment supplies `COLUMNS=120`, `LINES=40`, `ANSICON=120x40`, and `NO_COLOR=1` for unattended output formatting. These are not global Windows or application settings. Presentation and Database Availability run the same original PowerShell scripts with the same arguments; neither script was edited by this repair.

Each task's original XML was exported before changes. XML comparisons after removing only the Actions element confirmed no changes to principals, triggers, settings, overlap policy or task execution limits. All retain Charles / Interactive / Limited and their original one-minute plus sign-in triggers. `IgnoreNew` remains enabled. No Windows password was requested or stored, no account privileges were raised, and no Windows administrator/UAC elevation was needed for these owned-task changes. Tool sandbox access approval was needed for Task Scheduler/protected evidence access; it did not change Windows account privileges.

The launcher adds a per-job mutex. It waits for the original process and returns its exit code; nonzero failures remain visible in Task Scheduler. It drains output asynchronously, bounds captured output, and cleans up its command process on timeout or logging/stream failure. Internal cleanup deadlines are 295/175/55 seconds, five seconds before the unchanged 300/180/60-second task limits. Existing service processes intentionally launched by the support/recovery scripts retain their original lifecycle and port safeguards.

## Logging, rollback and files

Protected logs/evidence are outside the web root:

`C:\Users\Charles\FacultyConnect-Recovery\task-runtime`

That new directory's ACL grants access only to Charles, SYSTEM and Administrators. It contains original/updated XML, before/after console telemetry, financial/notification hash snapshots and per-job `*.runs.jsonl` / `*.output.log` files. Each active launcher log is capped at approximately 1 MiB with one previous generation; this retention affects only new launcher logs, never backups/recovery archives. Raw credentials are never supplied in launcher arguments or written into its source.

Files added:

- `tools/FacultyConnectTask.cs` and compiled `FacultyConnectTask.exe`: fixed-command GUI launcher.
- `tools/TaskConsoleProbe.cs` and compiled `TaskConsoleProbe.exe`: bounded diagnostic, not a scheduled service.
- `tools/audit-background-state.php`: CLI-only read-only database/ledger hashes and health checks.
- `docs/windows-background-task-repair.md`: this report.

`FacultyConnectTask.next.exe` is the staged build used for validation. The deployed executable is synchronized with its source and includes a prepared `backup` job, but **no backup task points to it without the requested separate approval**. Final build metadata/hash is in `task-runtime\launcher-build.json`.

Rollback evidence is the corresponding `*.before.xml` file. Restoring the old Actions would reintroduce console launches, so do not roll back merely to investigate a log. No application source, .env, secrets, database schema, firewall rule, tunnel credential or payment verification configuration was changed in this repair.

## Verification

Launcher tests verified output/error capture and exact exit propagation: success **0**, deliberate failure **7**, and killed/reaped timeout **124**. The launcher PE subsystem is **2 (Windows GUI)**. The original PHP remains subsystem 3, launched without creating a window.

One manual Task Scheduler execution was requested for every repaired task. Consecutive automatic cycles and their exit/window evidence are recorded in the final verification section below. The diagnostic will exit automatically; it has no persistence or task registration.

Read-only preservation checks during this repair showed:

- Main identity `ccis_local_20261018_164245`, port **3307**, `innodb_force_recovery=0`, data directory **`C:\Users\Charles\FacultyConnect-Recovery\20261012\restored-data\`**.
- Hash/count equality for users, payments, collections, members, submissions, receipts, payment accounts, water settings, audit logs and water notices.
- Water notice unread count **5 before / 5 after** and **zero duplicate non-null event keys**. Whole-row notice hashes include read states.
- Demo attempt and notification-outbox hashes unchanged. No new provider sessions or payments were created.
- Extended read-only checks include native notifications and trusted devices when those tables exist; final results are retained privately.
- Payment email remains disabled, and the original `--no-email` argument remains present. Successful maintenance output reports zero emails accepted and no checkout created.
- Focused notification/synchronization suites: **15 tests passed / 166 assertions**, including retry/deduplication and recipient/read-state authorization behavior. These use isolated test databases.
- Local login and public Cloudflare landing/login all returned **200**. The existing public hostname and cloudflared process were retained. Existing 2FA was not changed or bypassed; no real account sign-in was performed during this repair.
- The restricted Xendit relay checks passed, including denied paths, fixed public return, callback protection and cookie/debug stripping. The ngrok process/configuration remained unchanged.
- Existing database, application, relay, ngrok and Cloudflare PIDs remained stable; no duplicate service or persistent maintenance launcher was introduced. The pre-existing separate 3306 mysqld remains unchanged, not used by the application.

## Backup findings — separate, not silently repaired

**FacultyConnect Recovered Database Backup** targets the correct recovered instance and has a protected destination outside the web root. Its schedule is **08:00, 16:00 and sign-in**. Its runner uses a lock, single-transaction InnoDB export and refuses operation below **1 GiB** free. A quiet diagnostic reproduced exit **1** with about **603 MiB** free. At that measurement, at least **421 MiB additional** space was needed just to reach the minimum; **2 GiB or more free** is recommended for operating headroom. Earlier log entries include database-read failures and a later successful export on November 18, but current low-space failure is independently reproducible. No fresh successful backup/isolated restore is claimed.

The recovered backup runner does not automatically purge archives. Preserve every recovery/archive file. Safe next steps are approved Windows Storage cleanup outside protected data, or an approved external disk and separately reviewed backup destination change. Re-run the protected export and restore it to a new isolated schema with authorized credentials before declaring backup reliability restored. Do not lower the free-space safeguard.

**MySQL Auto Backup** is a legacy noon batch with no working directory. It targets **`eco_system.sql`**, supplies no explicit database port, uses a WMIC-derived timestamp and prints success regardless of the dump result. Its directory contains zero-byte exports and a malformed timestamp filename. It does not target the recovered main database. Its last result **2147946720 = 0x800710E0 / ERROR_REQUEST_REFUSED** means the operator/administrator refused the request; disabled Task Scheduler history prevents identifying the exact historical refusal condition. Its AC/battery restrictions are enabled, but that alone is not proof of the refusal cause. It should not be treated as a valid recovered-database backup.

Both backup task XML definitions were independently confirmed unchanged after the repair. Neither was deleted, disabled, replaced, rescheduled or reconfigured. No archive was deleted or moved. Separate approval has been requested to:

1. Point only the recovered backup's Action at `FacultyConnectTask.exe backup`, keeping its schedule, safeguards and real failure exit codes. This action is prepared and tested: it still returns 1 for the current low-space condition.
2. Disable the identified legacy wrong-target task, preserving its exported definition and all files. Disabling it is **not** a substitute for restoring the recovered backup routine.

Until approval, those daily backup actions remain potential console sources at their own schedules; the confirmed recurring minute-by-minute sources have been repaired.

## Visual confirmation and remaining limits

No agent desktop screenshot/visual observation is claimed. Windows console telemetry can establish console/window behavior but can miss very short-lived windows. The user has been asked to keep the normal desktop visible for at least three minutes and report whether any CMD/PowerShell/XAMPP window still appears. If one appears, record its time and visible title without closing unrelated services, then correlate it with the protected job logs.

Interactive tasks still require Charles signed in and the host awake/online. Cloudflare Quick Tunnel availability and hostname lifetime remain unchanged. The low-disk backup problem remains unresolved. No task history logging, protected credential access, unrelated pending migration, real email, Live Mode or withdrawal was enabled.

## Final scheduled-cycle evidence

All three manual scheduler runs succeeded. At least three consecutive automatic executions per task were confirmed:

| Task | Automatic start times | Results |
| --- | --- | --- |
| Payment Synchronization | 00:45:56, 00:46:56, 00:47:56 (and 00:48:56) | All 0 |
| Presentation Support | 00:45:27, 00:46:27, 00:47:27 (and 00:48:27) | All 0 |
| Database Availability | 00:46:12, 00:47:12, 00:48:12 | All 0 |

The 245-second final observation recorded **zero visible console handles for the repaired tasks and zero CMD process starts**. Every recorded launcher start reports no console attachment. Runs completed in under one second with no overlaps or timeouts. At 00:49:40 all task results remained 0, no launcher process remained, and the diagnostic had exited. Existing service PIDs remained stable. Both local and public login returned 200 in the final check.

Final read-only checks matched **all 12 checked tables**, including native notifications and trusted devices, against their captured baselines; the extended two-table baseline began during the observation. Water notice unread count remained 5; duplicate event keys remained 0; demo attempts/outbox remained unchanged. The recovered database identity/recovery checks remained correct and email remained disabled.

Final executable SHA-256: `1055234BB3DDE23A3A33EBD9A5D1266051AC8331F2835F79EF4483AFA5A3D31F`. Protected consolidated evidence: `task-runtime\repair-verification.json`. Current free space fluctuated to about **641 MiB** at the final check, still approximately **383 MiB below** the 1 GiB backup minimum. Neither backup task was modified. Visual desktop confirmation and backup-task approval remain pending, so daily backup console behavior is not claimed repaired yet.
