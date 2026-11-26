# Temporary presentation activation — November 19, 2026 (Asia/Manila)

Later November 19 update: the existing payment, presentation-support and database-availability tasks now use a verified windowless launcher with their original schedules, permissions and commands. See [background task repair evidence](windows-background-task-repair.md). The public tunnel and authentication configuration below remain unchanged.

## Current status

Cloudflare Quick Tunnel is active at **https://republic-formerly-arch-wayne.trycloudflare.com** and maps only to **http://127.0.0.1:8000**. No Cloudflare email gate, second login, new account, or public registration was added. Anyone with the link can view the landing/login pages. Existing account authentication, mandatory application 2FA, account usability/onboarding checks and role permissions still protect application features.

**Outside-host access confirmed by the user on November 19, 2026 (Asia/Manila).** In response to the phone-on-cellular check (Wi-Fi off), the user confirmed: “Landing/login and authenticated dashboard work.” This is user-reported separate-device verification, in addition to the hosting-computer HTTPS checks. The account role was not specified. The external web inspection service could not retrieve this domain; no agent-operated external browser verification or complete Faculty/Treasurer/Admin journey is claimed.

The user explicitly approved the exact APP_URL and public-request cookie changes and reported executing the administrator firewall command. The installed firewall rule was independently checked: enabled, inbound Block, TCP 3306/3307, all profiles. Local recovered database connectivity and local login still work. The pre-existing 3306 process was not stopped, started, or reconfigured. No public database tunnel was created. External port reachability was not independently scanned; the verified firewall block and loopback-only 3307 listener are the controls.

## Configuration applied

- APP_URL: `http://127.0.0.1:8000` → `https://republic-formerly-arch-wayne.trycloudflare.com`.
- `presentation-origin.json` stores that one approved origin privately. PHP and Node independently reject any other configured public origin. Tests ignore the installed file.
- Public application requests require that exact Host, a loopback connector, HTTPS forwarding and a valid Cloudflare client IP. Forwarded Host/Forwarded header injection is rejected. Proxy trust is limited to loopback and only protocol/client IP; the client IP is taken from the Cloudflare connector header rather than arbitrary incoming X-Forwarded-For.
- For that public host only, session and trusted-device cookies use Secure. Existing HttpOnly/session, host-only and SameSite=Lax settings remain. XSRF-TOKEN is Secure and host-only; it intentionally remains JavaScript-readable for CSRF clients.
- The middleware restores local cookie configuration after request handling. Local HTTP requests continue to use localhost redirects and the existing local cookie behavior. Existing session rows were not deleted. The different public host requires users to authenticate there normally.
- APP_DEBUG remains false. APP_KEY, main database connection, account credentials, roles, 2FA configuration, Xendit credentials, email delivery configuration and ngrok policy were not changed.

## Results

| Check | Observed result |
| --- | --- |
| Public `/` and `/login` | HTTPS 200 |
| `/dashboard`, `/dashboard/notifications/feed`, `/dashboard/water`, `/dashboard/members` without login | 302 to this public HTTPS login |
| POST login/logout without CSRF token | 419 |
| Unauthenticated demo webhook | 401 |
| `/.env`, `/.git/config`, `/tools/backup-recovered-database.php`, `/phpmyadmin` | 404 |
| `/storage/logs/laravel.log`, private SQLite/config paths | 403 |
| Unapproved public Host | 400 before session creation |
| Public session cookie | Secure, HttpOnly, host-only, SameSite=Lax |
| Anonymous CSRF/session persistence over two HTTPS requests | Passed; token values not retained in evidence |
| Login HTML HTTP/localhost link scan | Zero insecure/local absolute links |
| Four principal CSS assets | HTTPS 200 |
| Local dashboard redirect | `http://127.0.0.1:8000/login` |
| Automated suite | **228 passed, 2,293 assertions** |

Automated tests cover existing 2FA, account/role restrictions, other-user financial access, payment verification and notification behavior. New tests cover the exact public origin, Secure cookie configuration, trusted-proxy/client-IP restrictions, rejection of arbitrary hosts/forwarded-host injection and preservation of local policy. PHPUnit now explicitly uses `http://localhost` so the real public APP_URL cannot inadvertently route synthetic tests to an unapproved host. Tests retain isolated SQLite settings.

The agent did not use a real account to sign in during deployment. The user subsequently confirmed separate-device landing/login and authenticated dashboard access. Extended authenticated session persistence, logout, all role journeys, PDF/receipt downloads, mobile layout and browser mixed-content checks still require authorized user testing. Static link/asset checks do not substitute for those browser journeys.

## Xendit and notifications

The separate existing ngrok hostname remains **https://skid-porridge-snoring.ngrok-free.dev**, upstream **127.0.0.1:8005**. Its agent, credentials, webhook configuration and traffic policy were preserved. Root/login/config paths still return 404. The narrow relay was restarted to load its fixed public-origin return logic; ngrok itself was not restarted.

The stateless fixture return was verified over ngrok:

`GET /payments/xendit/demo-return/11111111-1111-4111-8111-111111111111`

returns **303** to:

`https://republic-formerly-arch-wayne.trycloudflare.com/dashboard/water/demo/11111111-1111-4111-8111-111111111111`.

The destination is fixed configuration, not a caller-supplied URL or forwarded host. Laravel's direct return handler and gateway preflight now expect the same approved destination. No success is inferred from the redirect; existing account ownership and server-side payment verification remain. `node tools/check-demo-ingress.cjs` passes denied paths, destination, callback protection and cookie/debug stripping. The tunnel starter's verification was updated to the same allowlisted origin.

No new provider session, payment submission, live transaction, withdrawal, or email was created. The actual full hosted checkout/return journey was not run. Test ledger counts remain two confirmed and one expired. Actual receipt audit remains two receipts / PHP 1,000, with no broken receipt references. The historic user hash differs from an older audit baseline; no claim is made that all historic user rows match that baseline. Seven other audited protected tables match it.

Existing one-minute notification maintenance remains enabled with `payments:maintain --no-email`. Payment email remains disabled. No notification schema changes or protected database administrator access occurred in this deployment.

## Services, recovery and limitations

Existing database availability and payment synchronization tasks remain. A new **FacultyConnect Presentation Support** task runs at Charles's sign-in and every minute as Charles, Interactive/Limited, ignoring overlapping executions. It uses a mutex and checks loopback ports before starting missing Laravel/relay processes, and reuses the existing narrow ngrok starter only when ngrok is absent. It never starts port 3306, changes database settings, creates payment sessions or publishes a new Cloudflare hostname. It refuses recovery below a 128 MiB storage safety floor. Its exercised LastTaskResult is **0**, and private status evidence confirms recovered database, application, relay, ngrok and Cloudflare are running.

Cloudflared itself reconnects transient network failures while its current process survives. **The current temporary URL cannot be guaranteed after process termination, Windows restart, shutdown or sleep.** Quick Tunnel recreation assigns a different hostname; it is deliberately not automated into an unapproved new public origin. A stable unattended URL requires a named tunnel/domain. See [Cloudflare Quick Tunnel limitations](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/).

All current interactive tasks require Charles to be signed in and Windows powered, awake and network-connected. Groupmates do not need multiple terminals while these services stay running. This deployment does not provide availability while the host is off/asleep.

Current free space is approximately **622 MiB**, below the recurring backup runner's **1 GiB** minimum. The backup failure remains unresolved. No archive was deleted/moved, no guard was lowered, and no new restore was performed. The prior successful notification-deployment backup/restore remains prior evidence only. See `remote-presentation-readiness.md` for measured cleanup candidates and protected recovery directories. Resolve capacity and then obtain/perform an authorized fresh isolated restore before calling long-term backup readiness complete.

## Presenter instructions

1. Use the HTTPS link above, without adding localhost or ngrok to the website address.
2. Sign in using your own authorized FacultyConnect account and existing 2FA method. Never share passwords or recovery codes.
3. Demonstrate only the functions assigned to your role. Online payment demonstrations remain Xendit Test Mode and need separate approval for new provider sessions.
4. If the page is unavailable, check the hosting PC is awake, signed in, online, and the existing Cloudflare process is still running. Do not publish a new arbitrary hostname or disable the firewall.

## Status / emergency shutdown

Read support status at `storage/app/private/presentation-tunnel/support-status.json`. Check scheduled task results, not just their registration:

```powershell
Get-ScheduledTaskInfo -TaskName 'FacultyConnect Presentation Support'
Get-ScheduledTaskInfo -TaskName 'FacultyConnect Recovered Database Availability'
Get-ScheduledTaskInfo -TaskName 'FacultyConnect Payment Synchronization'
```

For immediate public website shutdown, stop only the identified cloudflared process started with `tunnel --url http://127.0.0.1:8000`. Its PID at activation was **29092**; verify the executable and arguments before using a saved PID because Windows can reuse it. The support task does not restart Cloudflare. Leave database processes and archives intact. Stopping ngrok is a separate payment callback shutdown; do it only when payment callback outage is intended.

No cloudflared restart command is presented as preserving this temporary URL. A replacement hostname requires review and coordinated updates to the explicit origin allowlists, APP_URL and return expectations.

## Files/configuration changed

- `.env`: approved APP_URL only.
- `config/presentation.php`: fixed approved origin, inactive without private configuration.
- `storage/app/private/presentation-origin.json`: approved origin, no credentials.
- `app/Http/Middleware/RequireApprovedWebsiteHost.php`: public host/proxy/cookie boundary.
- `bootstrap/app.php`: host middleware after framework proxy initialization and before session routes.
- `app/Http/Controllers/XenditDemoController.php`: fixed public return.
- `app/Services/Payments/DemoXenditGateway.php`: public return preflight expectation.
- `tools/presentation-origin.cjs`, `tools/demo-ingress.cjs`, `tools/check-demo-ingress.cjs`, `tools/Start-MainDemoTunnel.ps1`: shared fixed allowlist and relay/start checks.
- `tools/Ensure-PresentationSupport.ps1`: limited idempotent support recovery.
- `tests/Feature/WebsiteHostBoundaryTest.php`, `phpunit.xml`: boundary tests and isolated test URL.
- `docs/temporary-presentation-activation.md`, `docs/remote-presentation-readiness.md`: evidence and limits.
- Windows task: `FacultyConnect Presentation Support`.
- Windows firewall rule created by user and verified by agent: `FacultyConnect Block Remote Database Ports`.

Private evidence: `storage/app/private/presentation-tunnel/https-verification.json` and `support-status.json`. These contain sanitized metadata, not credentials or cookie/token values.

## Readiness checklist

- [x] User-approved temporary public landing/login, without outer gate.
- [x] Firewall block verified; recovered local database retained.
- [x] Exact URL/proxy/cookie approval applied; HTTPS and anonymous session checks pass.
- [x] Protected routes/private-file denial and automated role/2FA tests pass.
- [x] Separate ngrok restrictions and fixed public return verified without new payment sessions.
- [x] Limited local support recovery installed/exercised; notification maintenance retained.
- [x] Separate-device public access confirmation: user reports landing/login and authenticated dashboard working on the requested cellular check.
- [ ] Authorized Faculty/Treasurer/Admin journeys and mobile/desktop browser/PDF checks.
- [ ] Disk capacity resolved; fresh backup/isolated restore and recurring backup succeed.
- [ ] Stable URL across restarts (not provided by the selected Quick Tunnel).
