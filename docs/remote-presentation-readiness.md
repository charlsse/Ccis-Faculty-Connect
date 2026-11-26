# Remote presentation readiness — November 18, 2026

**November 19 update: approved temporary tunnel activated; user confirmed separate-device landing/login and authenticated dashboard access. Backup readiness and extended browser journeys remain pending.** See [current activation results](temporary-presentation-activation.md). The findings below record the earlier inspection and approval gates; resolved items are superseded by that activation report.

## November 19 follow-up: temporary link selected

The user selected a temporary Cloudflare Quick Tunnel with **no Cloudflare email allowlist or extra login screen**. The landing/login pages may be public after security verification; protected features must retain the existing application accounts, mandatory 2FA and role permissions. This supersedes the earlier optional outer-access proposal.

A new Quick Tunnel process was allocated to `127.0.0.1:8000`, with loopback metrics at `127.0.0.1:20246`. Its hostname is `republic-formerly-arch-wayne.trycloudflare.com`. **This is not an activated or externally verified presentation link.** Application host restrictions remain in place; hosting-computer HTTPS requests to root, login, dashboard, configuration, log and backup-tool paths all returned 400. The external web inspection tool could not retrieve the login page. Logs/PID are under `storage/app/private/presentation-tunnel`, outside the public document root.

Exact approval requested before activation: change APP_URL from `http://127.0.0.1:8000` to `https://republic-formerly-arch-wayne.trycloudflare.com`; apply Secure session and trusted-device cookies only to that exact public host, retaining HttpOnly, host-only scope and SameSite=Lax; trust protocol forwarding only from loopback and reject unapproved public hosts. Local HTTP cookie behavior and local sessions must remain unchanged. No such changes have yet been applied. Remote users must authenticate with their existing account and 2FA. No account or main database settings changed.

The database firewall block is still absent. Port 3306 still listens on [::], and the existing Public-profile mysqld allow rules remain enabled. The administrator PowerShell command below has been presented to the user; successful execution and local database verification are required before publication. Free space is now approximately 635 MiB; backup readiness remains blocked.

The restricted local Xendit relay remains separate and unchanged. No external payment session was created.

## Findings and actions

An existing Cloudflare Quick Tunnel was already routing to `http://127.0.0.1:8000`. Its login page returned HTTP 200 through Cloudflare when tested by this hosting computer with certificate verification enabled. This is not an outside-device browser test. The external web inspection tool could not retrieve the URL. The public login response lacked Secure on both the session and XSRF cookies. APP_URL remains `http://127.0.0.1:8000`; APP_DEBUG is false. No saved configuration was found in Charles's `.cloudflared` directory. Installed cloudflared is 2026.10.7 and supports `--allowed-mail`.

The identified website Quick Tunnel was stopped. Another instance subsequently appeared under a PowerShell process parented by VS Code; that instance was also stopped. No Cloudflare Windows service or scheduled task was found. The unrelated interactive shell was not terminated. A new application host guard now rejects unapproved non-loopback Host values with HTTP 400 before session middleware. It preserves the older explicitly enabled exact-host webhook-only policy, whose own middleware still enforces its HTTPS/session and path restrictions. It prevents an unapproved hostname from serving application pages if a tunnel is restarted. This guard deliberately has no enabled public website hostname yet. Static public assets may still be served directly by the PHP development server; private files are outside its document root.

The recovered application database remains `ccis_local_20261018_164245` on **127.0.0.1:3307**, listening only on loopback. A separate pre-existing mysqld using `C:\xampp\mysql\bin\my.ini` is listening on **[::]:3306**. Two enabled Public-profile firewall rules allow mysqld TCP/UDP on any port from any address. This establishes a potential database network exposure, not proof of Internet reachability; external port reachability has not been verified. No database process, configuration, credentials, or connection settings were changed.

An attempted narrow inbound TCP block for ports 3306 and 3307 was denied by Windows (System Error 5: access denied). No firewall rule was added. Windows administrator execution is required for the following prepared command:

```powershell
New-NetFirewallRule -DisplayName 'FacultyConnect Block Remote Database Ports' -Direction Inbound -Action Block -Protocol TCP -LocalPort 3306,3307 -Profile Any -Description 'Protect local FacultyConnect databases; no database port is part of the public presentation.'
```

Verify the rule and confirm local 3307 connectivity afterwards. Do not start, reconfigure, or switch to port 3306. Do not disable the firewall to make the website work.

## Configuration approval required before activation

The request explicitly requires approval before APP_URL or session changes. None were made. The exact public hostname must first be selected. No presenter email allowlist has been supplied.

The proposed implementation, pending a concrete approved hostname, is:

- Set APP_URL from `http://127.0.0.1:8000` to the single designated HTTPS origin so worker-generated absolute links use the public site.
- Accept that exact public Host plus existing loopback hosts; reject every other Host. Trust protocol forwarding only from the loopback tunnel connector, and do not trust forwarded Host values or wildcard proxies.
- For public requests, use Secure, HttpOnly, host-only, SameSite=Lax session cookies and Secure trusted-device cookies. Keep existing local HTTP access supported by a separate local cookie policy/name. Do not globally turn on Secure cookies for localhost HTTP, which would break local sessions.
- Preserve session database rows and account credentials. A changed public cookie name/origin requires a fresh remote login and 2FA; existing local sessions should remain usable. Existing trusted-device cookies must also be reviewed for the public Secure policy.
- Keep APP_DEBUG=false, APP_KEY, DB connection settings, existing roles and 2FA unchanged.

This is a review proposal, not an implemented or approved configuration. The exact hostname, environment diff, cookie names and local/session impact must be presented before activation.

For a stable link and automatic recovery with the same hostname, a Cloudflare account/domain and a named tunnel are needed. A supported class-demo fallback is a Quick Tunnel protected with repeated `--allowed-mail` addresses. Cloudflare documents that Quick Tunnel hostnames change when recreated and have no uptime guarantee. Therefore a temporary tunnel cannot satisfy a stable unattended link across restarts. See https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/.

## Payment and notification status

The existing ngrok process and restricted mapping were preserved:

| Entry | Local destination / behavior |
| --- | --- |
| Main website (not published) | `127.0.0.1:8000` |
| Restricted Xendit ngrok hostname | `127.0.0.1:8005` |
| POST `/webhooks/payments/xendit-demo` | Narrow JSON relay to main application; callback authentication retained |
| GET `/payments/xendit/demo-return/{UUID}` | Fixed 303 to localhost transaction page; remote return still requires repair after public-origin selection |

`node tools/check-demo-ingress.cjs` passed forbidden routes, fixed return, unauthenticated callback protection and cookie/debug stripping after the host guard was installed. Ngrok configuration was not changed. No new payment sessions, provider payment requests, real emails, withdrawals, Live Mode changes or actual payment submissions were performed.

Remote return implementation remains pending: use one centrally configured exact HTTPS origin shared by the relay, Laravel return handler and preflight verifier. Never derive the destination from a request header, query parameter or arbitrary return URL. No financial success may be inferred from a browser redirect. Preserve server verification and isolated Test Mode accounting.

At inspection, the notification maintenance and recovered database availability tasks both had LastTaskResult 0 and one-minute schedules. Payment maintenance runs `payments:maintain --no-email`. Payment email delivery remains disabled. The backup task had LastTaskResult 1. Task registration alone is not evidence of a successful backup.

Existing data audit: two actual receipts totaling PHP 1,000; no receipt-link issues; two confirmed demo attempts and one expired attempt; demo notifications schema/outbox ready; wallet simulation disabled. The old audit baseline reports users different, while seven other protected tables match. This pre-existing baseline difference is not attributed to this deployment; preservation must be assessed against a fresh predeployment snapshot rather than claiming all historic user hashes match.

## Storage and backup blockers

Only C: is available. Free space measured about 675–699 MiB during inspection, below the recurring backup runner's **1 GiB** minimum. Do not lower that safeguard as a disk-space fix. Free at least 2 GiB of headroom before relying on recurring backups.

Recovery storage measured approximately 438 MiB for `FacultyConnect-Recovery\20261012`, and less than 1 MiB for recurring exports. Preserve both. Main project sizes include storage 233.5 MiB, presentation-assets 142.8 MiB, packages 75.2 MiB, vendor 63.9 MiB, and tmp 38.7 MiB. The presentation assets are user documents and must not be treated as disposable cache. Neither dependency tree is approved for deletion.

Potential cleanup candidates for separate approval are generated QA previews and QA-only dependencies under `storage/app/private/*-qa` and generated files in `tmp`. The largest QA directory is `water-qa` (89.1 MiB, including PDF/canvas dependencies), followed by `workspace-glass-qa` (28.1 MiB) and `ui-preview` (21.6 MiB). Review exact files and whether they remain needed before deleting. Even removing all project QA candidates may not provide adequate backup headroom. An approved external disk or Windows Storage cleanup is preferable. **Nothing was deleted or moved.**

The latest previously verified backup/restore is recorded in `docs/notification-deployment-completion-report.md`: protected directory `notification-deployment-20261118-162348`, restored into `fc_notify_restore_20261118_162348`, 24 tables checked and 18 stable table fingerprints matched. That is prior notification-deployment evidence, not a fresh restore verification for this remote deployment. Fresh backup/restore remains pending storage resolution and authorized isolated-restore credentials. The previous administrator credential authorization was expressly limited to notification migrations; those credentials were not reused for this task.

## Verification performed

Local HTTP checks after inspection:

| Path | Result |
| --- | --- |
| `/login` | 200 |
| `/dashboard`, `/dashboard/notifications/feed`, `/dashboard/water`, `/dashboard/members` | 302 to login for anonymous requests |
| `/.env`, `/.git/config` | 404 |
| `/storage/logs/laravel.log`, `/storage/app/private/xendit-demo-config.json` | 403 |
| `/tools/backup-recovered-database.php`, `/docs/notification-deployment-completion-report.md`, `/packages/autoload.php`, `/phpmyadmin` | 404 |
| `/login` with unapproved public Host | 400, before session creation |

Final automated suite: **226 tests passed / 2,287 assertions**, including two new host-boundary tests for unapproved public Host rejection and forwarded-host redirect injection. Existing webhook-only tunnel compatibility is preserved. These tests use isolated test settings, not production account logins.

No actual external authenticated Faculty/Treasurer/Admin browser journey, mobile responsive inspection, receipt/PDF download, public session persistence, logout, mixed-content check, or hosted payment return journey has been verified. Those must still be completed with authorized accounts and 2FA. No new demo account was created. Do not claim presentation readiness from local tests.

## Startup and shutdown

Existing working tasks cover recovered MariaDB and notification maintenance while Charles is signed in and Windows is awake. Laravel, restricted relay/ngrok and the approved future main website tunnel still need reviewed startup/recovery coverage. No new auto-start task was installed because publication and backup prerequisites remain unresolved. A Quick Tunnel restart changes the public hostname and requires deliberate reapproval/configuration; do not automate publishing arbitrary new hostnames.

For emergency website shutdown, identify `cloudflared` processes and stop only the main website tunnel. Keep the host guard until an approved public host/session configuration is deployed. Do not stop mysqld or delete backups. To stop payment callbacks deliberately, stop only the identified ngrok process and restricted relay; checkout preflight should then refuse new sessions. Never share tokens or raw protected configuration files with presenters.

Once ready, groupmates should receive one verified HTTPS link and use their own allowed email for the outer gate and their own FacultyConnect account/2FA. No shared admin credentials. The host computer must remain powered, awake, network-connected, and signed in for current interactive scheduled tasks.

## Files changed in this task

- `app/Http/Middleware/RequireApprovedWebsiteHost.php`: fail-closed loopback host boundary.
- `bootstrap/app.php`: prepend host guard before session handling.
- `tests/Feature/WebsiteHostBoundaryTest.php`: host/forwarded-host security checks.
- `docs/remote-presentation-readiness.md`: this report and concrete blockers/proposals.

No .env, APP_URL, session configuration, database settings, account, payment credential, webhook/ngrok configuration, recovery archive, or financial record was intentionally modified.

## Readiness checklist

- [x] Existing implementation inspected; main database and payment relay retained.
- [x] Unapproved application hostname rejected; identified website tunnels stopped.
- [x] Existing local login and payment relay checks pass.
- [ ] Windows administrator blocks external database ports and verifies local database remains healthy.
- [ ] Choose stable Cloudflare hostname/account or accept temporary-link limitations; supply presenter allowlist.
- [ ] Review and approve exact APP_URL/session/proxy/host configuration.
- [ ] Resolve disk capacity without deleting protected recovery data.
- [ ] Fresh protected backup and isolated restoration pass; recurring backup succeeds.
- [ ] Safe public-origin payment return implemented and tested without creating sessions.
- [ ] Approved startup/recovery installed with duplicate-process protection.
- [ ] External HTTPS and authenticated role/mobile/desktop journeys pass.
- [ ] Issue the one verified public link only after the above gates pass.
