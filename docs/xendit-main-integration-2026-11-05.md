# Main-site Xendit demonstration integration — 5 November 2026

## Payments UI refinement — 7 November 2026

The subsequent user-requested UI refinement is documented in `docs/payments-ui-refinement-2026-11-07.md`. Online-first checkout, collapsed manual QR, unified read-only history, neutral result screens and shared branded receipts/PDFs are implemented. Final regression: 199 tests, 1,916 assertions passed. Render-only desktop/tablet/mobile fixtures and both receipt PDFs were visually checked. No new external checkout was initiated by this redesign. Current read-only ledger inspection finds one confirmed demo attempt; this is not claimed as a new provider re-verification. Financial tables match the prior baseline; account/trusted-device/audit tables differ. Gateway services, webhook handling, credentials and tunnel configuration were preserved.

## Actual dashboard acknowledgement confirmed — 7 November 2026, 22:28 Asia/Manila

The user reported “received” after retrying the dashboard tests. Two new deliveries at 22:28:34 and 22:28:38 returned upstream and public HTTP 200. Laravel recorded both as ignored unmatched/unsupported events, not reconciled payments. The ledger remained empty and all 21 tracked main tables matched the preservation baseline. This verifies actual dashboard sample acknowledgement following the `data.id` correction. Hosted checkout creation and a genuine sandbox payment remain pending. The user may now start exactly one checkout through the existing authenticated FacultyConnect faculty flow and stop at the hosted page for inspection.

## Dashboard routing field correction — 7 November 2026, 22:27 Asia/Manila

Actual dashboard deliveries still failed after the initial unknown-sample change. The fresh diagnostic delivery established that `data.payment_session_id` was absent and `data.id` was a 27-character string, with a nonempty reference. Earlier probes incorrectly supplied `payment_session_id`, so their success did not prove compatibility with the dashboard.

The demo-only unrelated-event check now accepts either routing field and checks **all** supplied IDs plus the FacultyConnect reference for existing-attempt correlation. Any correlated event still enters the original strict validator; an alternate placeholder cannot hide a known session or reference. No field alias was added to provider settlement verification. Correctly authenticated unrelated events are ignored without writes.

Fresh full regression: **197 tests, 1,875 assertions passed** (`storage/logs/xendit-dashboard-id-suite.txt` and `.xml`), including attempts to hide a correlated session behind an alternate ID. External completed/expired probes using `data.id` now return 200 with the correct token; wrong tokens remain rejected. Ledger hash and all 21 tracked main tables remain unchanged, with zero attempts. The user was asked to retry actual dashboard deliveries after this correction. Actual dashboard success is still pending; do not create a payment session yet.

## Actual dashboard sample mismatch diagnosed and fixed — 7 November 2026

The subsequent dashboard tests reached Laravel at 22:18 and 22:21–22:22 Asia/Manila. Relay logs showed upstream **400**, mapped to public **503**. Callback authentication had passed; no provider retrieval was attempted. Safe field diagnostics showed a nonmatching envelope merchant, absent nested merchant, session ID outside the strict pattern, and nonmatching PAY/PHP/PH fields. Earlier canonical probes did not reproduce this dashboard sample shape. The disabled flag explained the initial tests, but did not explain these later enabled tests.

Added a demo-only authenticated-event routing hook: supported completed/expired events with bounded nonempty session/reference strings that match neither an existing demo session ID nor an existing demo reference are acknowledged as ignored before settlement-schema checks. No provider lookup or ledger mutation occurs. Events matching either identifier still pass the original complete merchant/session/amount/status validation and authenticated provider/capture verification. Wrong tokens still fail before this hook. The real gateway retains its default behavior. This receipt acknowledgement never represents payment success.

External canonical and placeholder fixtures now each return 200 with the correct token, and 503 with the wrong token (Laravel 401). Both completed and expired events passed; the ledger file hash remained unchanged. Full suite: **196 tests, 1,866 assertions passed**, evidence `storage/logs/xendit-dashboard-samples-suite.txt` and `.xml`. Main preservation comparison still matched all 21 tracked tables, with zero demo attempts.

The user has been asked for another actual dashboard Test and save delivery after this fix. At this handoff that external-provider confirmation is pending; do not create a session until it passes. The restricted tunnel and Test Mode flag remain on. No genuine sandbox session/payment/receipt has been created. Reference: [Xendit handling webhooks](https://docs.xendit.co/docs/handling-webhooks) distinguishes authenticated receipt acknowledgement from downstream processing.

## Restricted public callback verified — 7 November 2026, 22:12 Asia/Manila

After the user confirmed stopping the unused ngrok process, the restricted tunnel was restarted using the existing configuration. All denied-route/method, fixed local return, and disabled-callback checks passed. Activation evidence: `storage/app/private/demo-activation-20261107-221138/`; recorded ngrok PID 34680 (verify current identity before stopping).

The guarded operational helper enabled the existing private Test Mode flag. Public completed and expired diagnostic fixtures each returned 200 with the staged correct callback token; each wrong-token probe returned public 503, with relay logs confirming Laravel 401. Correct-token upstream responses were 200. The isolated ledger file hash did not change and its attempt count remained zero. All 21 tracked main tables still matched the November 7 baseline; migrations remained 14 and real receipts 2.

The endpoint is healthy for the existing webhook contract. This does not establish that the Xendit dashboard's own sample shape/token passes, or that a payment has completed. The user has been asked to retry both Test and save actions and, if successful, open exactly one hosted checkout through the main FacultyConnect account. Browser automation remains unavailable. At this handoff, the restricted tunnel is running and isolated Test Mode checkout is enabled; genuine session creation, provider payment/webhook capture verification, receipt/history acceptance, and presentation readiness remain pending.

## Webhook 503 diagnosis — 7 November 2026, 22:04 Asia/Manila

The user's Xendit dashboard sample deliveries returned `503 Verification unavailable`. Reproduction against Laravel directly and through the relay, followed by reason-only logging, established `demo_disabled`: `verifiedWebhookPaymentId()` checks gateway availability before token/body/ledger/provider processing, and the private activation flag was still false. These responses therefore do not establish a sample-session mismatch, bad callback token, provider outage, or ledger outage. The original relay did not log individual requests; historical samples cannot be inspected retrospectively.

The existing contract was retained: when enabled, valid authenticated unknown-session events return 200 with no provider lookup or payment mutation; malformed/mismatched samples return 400, wrong tokens return 401, and unavailable verification returns 503 internally. The relay intentionally maps every non-200 upstream response to public 503, so its public status alone cannot distinguish these causes. New relay diagnostics record only timestamp and status; Laravel logs fixed reason/outcome labels. No tokens, callback bodies, or account details are logged.

Added regression tests cover disabled callbacks and completed/expired unknown-session events, wrong tokens, replay, merchant mismatch, no outbound provider calls, and unchanged application tables. Full suite: **196 tests, 1,862 assertions passed**, `storage/logs/xendit-demo-webhook-suite.txt` and `.xml`.

A new operational probe initially reused a mutable HTTP client and merged the wrong/correct token headers, causing its authenticated public probe to fail. Checkout was disabled and the known demo tunnel process stopped. The probe now constructs a fresh client for every request. Subsequent local relay checks passed both events: wrong token upstream 401/public 503; correct token upstream/public 200. The ledger file hash was unchanged. These were local diagnostic fixtures, not Xendit dashboard deliveries or payments.

Public restart is pending review of a separate `ngrok.exe` PID 22164 with no tunnel arguments; the startup helper correctly refused to start alongside it. The user was asked to identify/close that process. It was not terminated. Demo activation is off, ledger attempts remain zero, and no checkout/payment/receipt has been created. Once the process is resolved, rerun restricted ingress checks, enable the approved Test Mode configuration, verify authenticated public fixtures, and request a fresh dashboard sample before the single real hosted sandbox session. Never represent a fixture acknowledgement as payment verification.

## Resumed preflight — 7 November 2026

The user authorized another restricted activation on their private connection. At 21:50 Asia/Manila, the approved ngrok domain passed nine denied GET paths and two denied POST paths (all HTTP 404), the stateless return fixture (HTTP 303 with the exact local result Location), and unauthenticated callback rejection (HTTP 503). Only the loopback relay on port 8005 is exposed. Inspection and remote management remain disabled. Evidence: `storage/app/private/demo-activation-20261107-215030/`; ngrok PID at activation: 31284. Verify process identity before stopping it.

DNS and HTTPS connectivity succeeded with certificate validation enabled. A read-only authenticated Xendit session lookup returned HTTP 404 with `NOT_FOUND` for a deliberately nonexistent session. No session was created by this probe. The existing Test Mode key, callback token and approved return origin are staged. The local relay was stopped at the start of this turn and was restarted; local ingress checks then passed. Main MariaDB identity remains `ccis_local_20261018_164245`, port 3307, recovery mode 0. Home/login HTTP 200 was verified earlier in this conversation; authenticated browser acceptance remains pending.

The public return now works, but the earlier failure's exact cause remains unknown because that run retained neither response status nor Location. This is not evidence that the university network caused it. The preflight now uses the same explicit stateless UUID fixture as the local relay test and preserves the public response body privately. The relay intentionally does not require a ledger row for this redirect; this probe does not represent a payment or successful result page.

The November 6 preservation baseline differs only in `users`, `trusted_devices`, and `audit_logs` before this attempt; financial tables still match it. A new 21-table baseline was captured at `storage/app/private/demo-preservation-20261107.json`, and the subsequent comparison matched all tracked tables. Sessions/cache are excluded. Migration count remains 14, real receipt count 2, demo attempt count 0, and demo activation remains off.

Fresh regression result: **194 tests, 1,825 assertions, all passed**. Evidence: `storage/logs/xendit-demo-suite-20261107.txt` and `.xml`. Operational helper updates allow dated preservation baselines and report changed table names without row data; `tools/demo-provider-preflight.php` performs only a read-only credential/connectivity probe.

Both browser and desktop automation failed to initialize with `failed to write kernel assets: The system cannot find the path specified. (os error 3)`. The user has been asked to sign in normally and configure/confirm the approved Test Mode Payment Session completed/expired callback destination. Provider settings have not been changed by the agent. Checkout activation, a genuine hosted session, simulated payment, authenticated provider webhook, verified receipt/history, and desktop/mobile visual acceptance remain pending. The restricted tunnel is running for this handoff; demo checkout remains disabled. Presentation readiness is **not established**.

## Approved activation attempt — 6 November 2026

The user approved the restricted domain, two demo routes and Test Mode webhook update. Approval is recorded; it is no longer the blocker.

At approximately 18:55 Asia/Manila, the restricted ngrok endpoint was started using the existing private ngrok token and the validated `tools/demo-ngrok.example.yml`. The local-only upstream was `127.0.0.1:8005`; the main application remained on port 8000. No wider application routes were added.

The startup check passed nine denied GET paths and two denied POST paths, then failed its required public return check (expected HTTP 303 and the fixed local result URL). The script immediately stopped its ngrok process. No public tunnel remained at the subsequent process check. In accordance with the user's stop-on-verification-failure instruction, public access was not reopened.

The failed run did not retain the public return status/Location, so the exact external response and root cause are **not established**. The script now records safe status codes and destination-match booleans incrementally for any future authorized diagnostic attempt. This diagnostic improvement has not been used to reactivate the tunnel.

Local follow-up passed: the same return-path validation against port 8005 returned HTTP 303 and the expected `http://127.0.0.1:8000/dashboard/water/demo/{UUID}` destination. The main home page returned HTTP 200. Local ingress denial/authentication checks passed. The ngrok configuration validator accepted the configuration. None of these local checks establishes successful public return handling.

Before activation, the main identity was verified as `ccis_local_20261018_164245`, port 3307, recovery mode 0. A read-only preservation baseline covered all 21 main tables except volatile sessions/cache tables. The post-stop comparison matched every table's row count and SHA-256 hash, including accounts, contributions, receipts and wallet tables present in that schema. Main migration count remained 14 and real receipts remained 2. The demo ledger still has **zero attempts**, and demo activation is **off**.

Final outcome of this attempt:

- Genuine sandbox payment: **not started**.
- Payment webhook received: **no**; only local unauthenticated/disabled endpoint probes were performed.
- Xendit dashboard callback configuration: **not changed**, because endpoint verification failed first.
- New verified result, demo receipt, faculty history entry or Treasurer transaction: **none**.
- Real dues, real records and wallet balances: **unchanged**, verified by the preservation comparison.
- Public tunnel: **stopped automatically**.
- Primary-Xendit checkout promotion: **not activated**, because the user made it conditional on successful verification.
- Presentation readiness: **not yet ready for an end-to-end Xendit demonstration**.

Evidence is private under `storage/app/private/demo-activation-20261106-185510/stopped.json`; the preservation baseline is `storage/app/private/demo-preservation-20261106.json`. New helper files: `tools/demo-preservation.php` and `tools/Start-MainDemoTunnel.ps1`. The prior 194-test result below remains historical; this turn changed only operational helpers and this report, not payment or authentication application code.

The public-return failure is the current blocker. Investigate the restricted endpoint's status and fixed return destination before any checkout creation; never broaden allowed routes to work around the failure.

---

Implemented and regression-tested; **not yet activated or verified against a new provider transaction**.

## Completed code

The existing main login, onboarding, mandatory 2FA and permission middleware protect the new demonstration routes. The main contribution checkout has a separately labeled Xendit Test Payment card, followed by the preserved manual Treasurer QR workflow. The demo button stays disabled until its configuration is activated. There is no separate user-facing application or login.

The demo service resolves the amount and owner from the existing contribution. Browser amounts/user IDs are ignored. An immediate SQLite transaction and unique active-member constraint reserve one unresolved attempt. Creation is never automatically retried after a lost response. Authenticated callbacks can recover a creation with an unknown response using the server-generated reference.

`DemoXenditGateway` reuses `XenditGateway` session creation, approved checkout-host validation, callback-token checks and linked Session / Payment Request / Payment capture verification. Small protected extension points select credentials, return origin, database identity guard and attempt model. Capture validation rules remain intact. A verified channel code is displayed when supplied by the Payment API.

The existing `WaterGatewayService` is **not** used to settle demo payments. Its synthetic-database safeguards remain in place. Demo settlement changes only the separate ledger. It never invokes real submissions, receipts, wallet credit, contribution status changes or notification/audit writes in the main database.

Results use persisted provider-verified state. Browser returns never confirm payments. Faculty history is owner-filtered; Treasurer and Administrator oversight uses existing `payments.view`. Confirmed results have unique `DEMO-{UUID}` receipts with faculty name, contribution, amount, method, provider payment reference and verified date. Print / Save as PDF uses the browser print dialog; there is no separate server-generated PDF endpoint.

The historical verifier conservatively retains canceled or unresolved failed provider attempts as pending. Verified expiry releases only the demo reservation; late expiry cannot reverse success. The view supports a failed label, but no new provider-failure classification was introduced merely to display that label.

## Database and credentials

- Actual MariaDB identity checked before preparation and again after tests: `ccis_local_20261018_164245`, `127.0.0.1:3307`, `innodb_force_recovery=0`.
- Main migration count remained 14; real receipt count remained 2. These counts are limited observations, not a full live-data checksum.
- Isolated connection: `xendit_demo`, SQLite at `storage/app/private/xendit-demo.sqlite`.
- Only `demo_attempts` exists in that ledger. Main-schema user/member IDs and source database identity are server-controlled correlations; no synthetic users are inserted in the main database.
- Additive schema file: `database/demo/001_create_demo_ledger.php`, explicitly outside the normal migrations directory.
- Tested schema installation in a disposable SQLite database. A pre-install SQLite backup was opened independently and integrity-checked; the installed database was exported with SQLite `VACUUM INTO` and its schema checked.
- Evidence: `storage/app/private/demo-preparation-20261105-204900/verification.json` and sibling SQLite files. This backup is for the new demo database, **not** a backup of recovered MariaDB.
- No new main-database migration was applied, and none is required by this design. Existing recovery and backup infrastructure was not modified.
- `.env`, APP_KEY, main connection credentials and session configuration were not edited.
- Existing test credentials were copied into `storage/app/private/xendit-demo-config.json`; activation remains `false`. Do not publish this file or the private storage directory. Automated testing does not load this private configuration.

## Routes

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/dashboard/water/members/{member}/pay` | Existing checkout; demo option added |
| POST | `/dashboard/water/members/{member}/demo` | Authorized main-account checkout creation |
| GET | `/dashboard/water/demo` | Own history or authorized oversight |
| GET | `/dashboard/water/demo/{attempt}` | Server-recorded result; attempt is a UUID |
| POST | `/dashboard/water/demo/{attempt}/check` | Throttled provider recheck |
| GET | `/dashboard/water/demo/{attempt}/receipt` | Confirmed demonstration receipt |
| POST | `/webhooks/payments/xendit-demo` | Token-authenticated demo callback |
| GET | `/payments/xendit/demo-return/{reference}` | Fixed local return, no status mutation |

## Verification actually performed

- `php packages/phpunit/phpunit/phpunit --log-junit storage/logs/xendit-demo-suite.xml`: **194 tests, 1,825 assertions, all passed**. Text output: `storage/logs/xendit-demo-suite.txt`.
- Includes existing Xendit, wallet, manual-payment, trusted-device and authentication tests, plus 11 new demo tests.
- Demo tests cover authoritative amount, single outbound creation, response-loss recovery, authorization, 2FA middleware, private history, Treasurer overview, receipt contents, return spoofing, wrong callback token, wrong merchant/amount, capture mismatch, expiry, late expiry and live-key/wrong-port rejection.
- A mock-provider successful webhook and duplicate replay compared every application table in the in-memory main test database before/after: no main-table changes.
- One pre-existing onboarding assertion expected old trust-device wording. Updated it to assert the actual label and `trust_device` input; no authentication implementation or trust duration changed.
- `node tools/check-demo-ingress.cjs`: passed seven forbidden paths, fixed local return, unauthenticated callback rejection and cookie/debug stripping.
- `http://127.0.0.1:8000` returned HTTP 200 after startup.
- Main database recheck remained port 3307 / recovery off.

These are automated/provider-mocked and local HTTP checks. **No new genuine provider session, hosted payment, public webhook delivery or capture has been observed in this task.** The October 18 synthetic transaction is historical evidence only and is not represented as verification of this main-site flow.

The browser tool reported `The browser access host is unavailable`. Authenticated desktop/tablet/mobile rendering and keyboard interactions were not visually verified. No screenshots of a new successful payment or receipt were produced. Responsive CSS and rendered HTML assertions are not substitutes for visual acceptance.

## Prepared public exposure — approval still required

No public tunnel was started or reconfigured. The local relay listens only on `127.0.0.1:8005`.

Proposed origin: `https://skid-porridge-snoring.ngrok-free.dev`

Only:

1. `POST /webhooks/payments/xendit-demo` — forwards JSON (maximum 64 KiB) and callback token to the main application's demo handler. It strips cookies and forwarded headers; responses contain no application debug body or session cookie.
2. `GET /payments/xendit/demo-return/{UUID}` — redirects directly to `http://127.0.0.1:8000/dashboard/water/demo/{UUID}`. No login or ledger data is exposed publicly.

Everything else is rejected by the relay. The prepared ngrok policy adds edge filtering. Inspector storage and remote management are disabled. Port 8000 must never be the tunnel upstream. TLS remains provided by ngrok; outbound Xendit HTTPS verification remains enabled.

Before a real demonstration: obtain approval to activate this exposure, confirm the hostname is still available, securely supply the existing ngrok token, validate the prepared configuration with the installed ngrok version, and start only the named demo endpoint. Update the Xendit **Test Mode** Payment Session completed/expired callback URL to:

`https://skid-porridge-snoring.ngrok-free.dev/webhooks/payments/xendit-demo`

The old synthetic `/webhooks/payments/xendit` endpoint cannot process these attempts. Existing dashboard callback configuration has not been changed. Confirm dashboard token matches the staged private token without printing it. Enable the private demo flag only after ingress is working. If account authentication is required, the owner must complete it.

## Local startup

From PowerShell:

```powershell
& 'C:\Users\Charles\FacultyConnect-Recovery\20261012\Start-RecoveredMariaDB.ps1'
Set-Location 'C:\xampp\htdocs\CCIS_CONNECT'
php tools/demo-identity.php
php tools/install-demo-ledger.php
php artisan serve --host=127.0.0.1 --port=8000
```

The installer retains an already-installed demo ledger. Do not migrate or seed the main database. The main service and MariaDB were started in this task; avoid starting duplicate listeners.

In another terminal, the local-only ingress can run as:

```powershell
node tools/demo-ingress.cjs
```

It is already running locally from this task. `node tools/check-demo-ingress.cjs` verifies the disabled/unauthenticated ingress. To stop it, terminate only that known Node process (PID recorded during this task: 27768; verify current process identity before stopping). Do not stop unrelated Node processes. The public ngrok template is `tools/demo-ngrok.example.yml`; it has no token and was not activated.

## Manual acceptance after approved activation

1. Sign in to the main site with an existing FacultyConnect account and its normal 2FA/trusted-browser flow.
2. Open Payments & Collections and a published contribution. Record the real amount/status.
3. Confirm the Xendit card and separate manual QR workflow. Select test-mode acknowledgement and start checkout once.
4. Confirm the provider-hosted Test Mode URL. Use only the provider's supported test simulation. Capture the hosted-checkout screenshot before completing it.
5. Return to the fixed main-site URL with the existing session. Pending is valid until verification; use Recheck if callback delivery is delayed.
6. Observe the authenticated callback and verified linked Session/Request/Payment capture before accepting a successful result.
7. Open and print/save the DEMO receipt. Check faculty name, reference, amount and date. Capture result and receipt screenshots.
8. Check member history; use a different existing faculty account to confirm access denial. With an existing Treasurer account, verify the sandbox overview separately from real totals.
9. Confirm the real contribution status, real receipts, wallet and outstanding totals remain unchanged. Verify desktop, tablet and mobile layouts and keyboard focus; capture checkout/history/overview screenshots.
10. Disable demo activation and stop only the public demo tunnel when the demonstration is over. Retain the ledger and evidence.

Provider reference: [Create a session](https://docs.xendit.co/apidocs/create-session) documents hosted test checkout URLs; [Simulate payment — test mode](https://docs.xendit.co/apidocs/simulate-payment-test-mode) documents the test simulation endpoint and asynchronous result. No simulation endpoint was invoked in this task.

## Exact source files changed/added

Modified:

- `app/Services/Payments/XenditGateway.php`
- `bootstrap/app.php`
- `config/database.php`
- `routes/water.php`
- `resources/views/water/checkout.blade.php`
- `resources/views/water/index.blade.php`
- `resources/views/water/partials/navigation.blade.php`
- `public/css/unified-payments.css`
- `tests/Feature/AccountWorkflowTest.php`

Added:

- `app/Models/XenditDemoAttempt.php`
- `app/Services/Payments/DemoXenditGateway.php`
- `app/Services/Payments/XenditDemoService.php`
- `app/Http/Controllers/XenditDemoController.php`
- `config/xendit_demo.php`
- `database/demo/001_create_demo_ledger.php`
- `resources/views/water/demo/option.blade.php`
- `resources/views/water/demo/index.blade.php`
- `resources/views/water/demo/show.blade.php`
- `resources/views/water/demo/receipt.blade.php`
- `tests/Feature/XenditDemoTest.php`
- `tools/demo-identity.php`
- `tools/install-demo-ledger.php`
- `tools/stage-demo-config.php`
- `tools/demo-ingress.cjs`
- `tools/check-demo-ingress.cjs`
- `tools/demo-ngrok.example.yml`
- `docs/xendit-main-integration-2026-11-05.md`

Generated runtime artifacts additionally include the private ledger/configuration/preparation files, test logs, compiled Blade cache and local-service logs. No Git repository was present, so this file list records this task's explicit edits rather than a Git diff.
