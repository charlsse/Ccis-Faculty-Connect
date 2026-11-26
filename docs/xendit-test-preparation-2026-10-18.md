# FacultyConnect Xendit Test Mode preparation — October 18, 2026

## Result

Recurring backups are configured and restore-tested. Xendit Payment Sessions hosted checkout is implemented behind disabled configuration and tested with mocked HTTP responses and synthetic SQLite records. **No real Xendit request, transaction, public tunnel, or migration against MariaDB was run.** The normal `.env` is byte-identical to its deployed cutover backup. APP_DEBUG remains false.

The recovered database remains `ccis_local_20261018_164245` on port 3307: **23 application tables, 281 source rows, six users, zero historical payments**. All 23 full-content hashes still match the new backup's restored data. No gateway table exists there. Landing and login smoke requests succeeded; only ordinary anonymous sessions were created. Original/recovery archives were not deleted or overwritten.

See [backup schedule, verification, and restore procedure](recovered-database-backups.md). Protected output is `C:/Users/Charles/FacultyConnect-Recovery/recurring-backups`. The scheduled task completed successfully; operation requires Charles signed in and recovered MariaDB running.

## Existing workflow inspection

Inspected `routes/water.php`, `WaterCollectionController`, `WaterPaymentController`, `WaterGatewayController`, `WaterPaymentService`, `WaterGatewayService`, the Water models/policies, roles, original water migrations, optional PayMongo migration, payment views, and existing tests.

Manual contribution submission, proof handling, Treasurer approval/rejection, cash recording, receipts, reports, and the historical payments module remain in place. Online attempts use the existing reservation and reconciliation service. No existing financial records were edited. Administrator/President remains the existing `admin` role with `payments.view`; only Treasurer has `payments.manage`. Xendit initiation additionally requires the assigned user to have the `faculty` role. No payout or recipient-editing UI was added.

## Official API approach

The adapter uses `POST https://api.xendit.co/sessions`, `session_type=PAY`, `mode=PAYMENT_LINK`, PHP/PH, automatic capture, and disabled payment-method saving. Amounts are converted from internal integer centavos into API currency units. A unique local UUID becomes the session reference. HTTPS success/cancel URLs point to a read-only return page. Only documented test checkout hosts are accepted. See [Create Session](https://docs.xendit.co/apidocs/create-session) and [Get Session](https://docs.xendit.co/apidocs/get-session), reviewed October 18, 2026.

Session events use `payment_session.completed` and `payment_session.expired`. The endpoint authenticates the **x-callback-token** with constant-time comparison, checks business/session/reference/amount identity, and retrieves the session again using server credentials. See [session webhook schema](https://docs.xendit.co/apidocs/webhook-notification-sent-defined-webhook-url-updates-payment-session) and [Xendit's callback-token verification guidance](https://help.xendit.co/hc/en-us/articles/360038072991-How-to-validate-if-the-webhook-is-sent-from-Xendit).

A completed PAY session also requires authenticated `GET /v3/payment_requests/{id}`, API version `2024-11-11`, matching business, amount, currency, country, payment identity, successful status and full successful capture. The latest linked request is preferred after a retry. See [Get Payment Request](https://docs.xendit.co/apidocs/get-payment-request). No browser query or callback body alone settles a contribution.

## Payment behavior and safeguards

- Disabled by default; accepts development/test keys only. There is no live-mode path.
- Xendit refuses the recovered database even if someone turns on the enable flag. It requires an explicitly named `ccis_xendit_test_*` database on 127.0.0.1:3307, the matching `XENDIT_TEST_DATABASE`, a non-production environment and operator confirmation that it contains only synthetic contributions. Before API calls, it checks the actual database identity. Automated tests use SQLite `:memory:`.
- Server-owned assigned amount and collection status determine eligibility. Browser amount overrides are ignored. Creation is once per unresolved reservation; ambiguous network/create responses remain `unknown` and are not retried automatically.
- API requests use Basic authentication, fixed HTTPS API host, timeouts, and no redirects. Only synthetic customer details are sent by this preparation adapter. Secrets and provider response bodies are not logged.
- Pending attempts reserve the contribution against simultaneous manual/online settlement. Confirmation uses existing row locks, unique references/provider IDs and receipt constraints. Duplicate and late callbacks do not create extra submissions, receipts or notices.
- An expired session releases its reservation only if there is no linked payment, or its verified payment is terminal without captures. Pending/captured/ambiguous payments and canceled sessions remain reserved for investigation. Payment-failure events do not mean a whole retryable session has expired.
- Unknown event types are acknowledged without settlement. Wrong token returns 401; malformed/mismatched notifications return 400; provider verification failures return 503 for retry. Payloads over 64 KiB are rejected. The webhook has a narrow CSRF exception and rate limit.
- Verified synthetic payments use `xendit_test`; their receipts explicitly say TEST and are not valid evidence of real contribution dues. Manual Treasurer approvals and PayMongo behavior remain supported.
- Xendit credits the merchant business associated with its credentials; the application cannot prove ownership of that external account. The Treasurer must confirm the configured business/account before enabling tests. `XENDIT_BUSINESS_ID` is matched in responses/callbacks; no `for-user-id`, split or payout destination is accepted from users. Admin/President gains no financial-management permission.

## Migration — prepared, not applied to MariaDB

`database/experimental/xendit/2026_10_18_180000_add_xendit_gateway_attempts.php` is opt-in. It delegates to the existing shared gateway schema: a new `water_gateway_attempts` table with references, centavos, provider identifiers, encrypted checkout URL, status, unique active-member reservation, foreign keys and timestamps. It also makes receipt `verified_by` nullable so automatic verification never impersonates the Treasurer.

Do not apply both the PayMongo and Xendit setup migrations. If the shared table already exists, the Xendit setup refuses. No broad/default migration batch is necessary. Rollback of an empty setup is tested; rollback refuses to discard gateway history or automatic receipts. No migration command was run against the real database. Test framework schema operations were confined to its guarded in-memory SQLite database. MariaDB-specific DDL/concurrency testing remains for a separately approved synthetic database.

## Environment configuration

Only `.env.example` was changed; actual secret values were neither requested nor added. Set these **only in a separate protected test configuration**, after approval to activate provider testing:

| Variable | Required value/purpose |
| --- | --- |
| PAYMENT_GATEWAY_ENABLED | Remains false now; explicit true only for approved synthetic testing |
| PAYMENT_GATEWAY_PROVIDER | `xendit` |
| PAYMENT_GATEWAY_ENV | `sandbox` only |
| PAYMENT_GATEWAY_SANDBOX_DATA_CONFIRMED | true only after confirming no real faculty/contributions in test database |
| XENDIT_SECRET_KEY | Treasurer-owned merchant's Test Mode secret; environment only |
| XENDIT_CALLBACK_TOKEN | Test dashboard webhook verification token; environment only |
| XENDIT_BUSINESS_ID | Exact merchant business identifier, checked on responses |
| XENDIT_TREASURER_ACCOUNT_CONFIRMED | false now; Treasurer confirmation required |
| XENDIT_TEST_DATABASE | Exact separate `ccis_xendit_test_*` name; never the recovered production-intended database |
| DB_HOST / DB_PORT / DB_DATABASE | 127.0.0.1 / 3307 / approved synthetic database with scoped account |
| APP_URL / APP_DEBUG | Exact approved HTTPS origin / false |
| SESSION_COOKIE | Separate fresh name beginning `facultyconnect_xendit_test_` |
| SESSION_SECURE_COOKIE | true when HTTPS is activated |
| SESSION_HTTP_ONLY / SESSION_SAME_SITE | true / lax |
| SESSION_DOMAIN | null/host-only; no broad parent-domain cookie |
| TUNNEL_ENABLED / TUNNEL_HOST | false now / later the exact assigned hostname, no wildcard |

Do not paste API keys, callback tokens, ngrok tokens or DB passwords into source, commands, chat, screenshots, request logs or this report. Keep the recovered application's APP_KEY and configuration unchanged. Use a separate test key/storage/session store for a synthetic application process.

## Xendit dashboard steps — pending

1. Select **Test Mode** in the Treasurer-controlled merchant account and verify its business identity. Generate a test secret with only the permissions needed to create/read Payment Sessions and read Payment Requests. Do not provide payout/disbursement access.
2. After a public test endpoint is separately approved, register the Payment Sessions webhook URL: **`https://ASSIGNED-HOST/webhooks/payments/xendit`**, for completed/expired session events. Put its verification token in the private test environment. This is not the old invoices callback or PayMongo endpoint.
3. The adapter supplies **`https://ASSIGNED-HOST/payments/xendit/return`** for success/cancel navigation. This page never confirms payment. No real faculty/customer details should be entered in hosted checkout.
4. Exercise the provider's Test Mode simulator only after approval, confirm real callback/API schemas and capture fields, and repeat duplicate/failure/expiry checks. No dashboard changes or remote transaction were performed in this phase.

## ngrok preparation — do not start yet

No ngrok executable was found on the inspected PATH, and no ngrok/cloudflared process was running. Installation/account/domain configuration remains pending. The prepared command below is for a later approved synthetic test process bound to port 8000; **do not tunnel the currently running recovered-data application**:

```powershell
ngrok http http://127.0.0.1:8000 --url https://ASSIGNED-HOST --inspect=false --traffic-policy-file tools/ngrok-xendit-policy.yml
```

The [current ngrok CLI](https://ngrok.com/docs/gateway/agent/cli) documents `--url`, `--inspect` and `--traffic-policy-file`. Preserve the public Host; do not use a Host rewrite. Configure the ngrok token privately, not as a command argument. Disable/request-redact any cloud traffic capture too; local `--inspect=false` alone is not an audit of cloud retention.

The edge policy permits only POST webhook and GET return, rejecting other paths before upstream, including static files that bypass Laravel. It uses ngrok's [custom response action](https://ngrok.com/docs/gateway/traffic-policy/actions/custom-response). The Laravel middleware independently checks exact host, loopback proxy address, forwarded HTTPS, debug=false, Secure/HttpOnly/SameSite cookies, and synthetic gateway configuration. Only loopback proxy addresses are trusted; forwarded Host is not trusted. Rewritten local Host with forwarding headers is rejected. Edge policy has not been executed against ngrok and must be verified when activation is approved.

Login, administrator pages, financial reports, health/diagnostic endpoints and uploaded assets are intentionally unavailable through the public hostname. Checkout initiation stays local. Because Secure cookies require HTTPS, the later browser session must use a protected local HTTPS origin for its synthetic app; do not weaken cookies to make a plain HTTP login work. A loopback TLS front end or a separately reviewed restricted browser-access policy is still needed for an end-to-end hosted browser trial. The current local recovered application remains on its original loopback HTTP configuration.

## Automated tests and limitations

Targeted test command:

```powershell
& C:\xampp\php\php.exe packages/phpunit/phpunit/phpunit tests/Feature/XenditGatewayTest.php tests/Feature/PayMongoGatewayTest.php tests/Feature/WaterCollectionTest.php tests/Feature/PaymentModuleTest.php --no-progress
```

All outbound Xendit requests are mocked and stray requests are prohibited. Tests cover creation/server amount, ownership/role restrictions, capture verification, successful/failed/expired/duplicate/late/malformed/unauthorized notifications, pending-payment expiry, mismatches, latest payment retry identity, lost creation responses, forbidden live keys/databases/URLs, redirect non-settlement, test receipt labels, migration rollback guards, and HTTPS host/proxy/public-route restrictions. Existing manual payment and PayMongo regression tests also run.

**Final result: 68 tests, 679 assertions passed.** This includes 15 Xendit/tunnel/schema tests and the existing PayMongo/manual payment suites. All 12 changed/new runtime PHP files also passed syntax checks.

One pre-existing test expected “Report & CSV export”; the existing report page already said “Report & Excel export”. The assertion was aligned with the current UI; report behavior was not changed.

Remaining limits: mocks are not an actual Xendit contract test; provider credentials/dashboard verification, synthetic MariaDB migration/concurrency validation, protected local HTTPS and approved ngrok exposure are pending. There is no live processing, payout, refund or production activation in this work. The historical MariaDB crash remains unresolved, and same-drive backups do not protect against drive loss.

## Changed files

| Files | Change |
| --- | --- |
| `tools/backup-recovered-database.php` | Recurring verified backup runner |
| `app/Services/Payments/XenditGateway.php` | Test-only official Payment Sessions adapter |
| `config/payment_gateway.php`, `.env.example` | Disabled Xendit configuration and placeholders |
| `app/Providers/AppServiceProvider.php` | Select the configured adapter |
| `app/Services/Payments/WaterGatewayService.php` | Provider-aware existing reconciliation, faculty-only Xendit creation |
| `app/Http/Controllers/WaterGatewayController.php` | Provider-specific safe URLs and webhook routing |
| `routes/water.php`, `bootstrap/app.php` | Xendit webhook/return, narrow CSRF exclusion and tunnel middleware |
| `app/Services/WaterPaymentService.php` | Xendit test receipt verification label |
| `resources/views/water/checkout.blade.php`, `online-status.blade.php`, `partials/gateway-setup.blade.php` | Provider-neutral test checkout messaging and safe link handling |
| `resources/views/water/receipt.blade.php`, `receipt-pdf.blade.php`, `review.blade.php` | Xendit TEST receipt/submission labels |
| `database/experimental/xendit/2026_10_18_180000_add_xendit_gateway_attempts.php` | Optional additive schema setup and guarded rollback |
| `app/Http/Middleware/RestrictTestTunnel.php`, `config/tunnel.php`, `tools/ngrok-xendit-policy.yml` | Disabled tunnel preparation and public-route restriction |
| `tests/Feature/XenditGatewayTest.php` | Mocked integration/security tests |
| `tests/Feature/WaterCollectionTest.php` | Correct existing report-label assertion |
| `docs/recovered-database-backups.md`, this report | Backup runbook, API approach, setup, results and blockers |

Outside the repository: a new scoped backup account, protected `backup.env`, recurring-backups directory/artifacts, retained backup-verification schema, one-off verification script, and Windows scheduled task were created. Existing recovery archives and original database files were not modified.
