# Payment synchronization and notification verification

## Current completion status

**Update after explicit approval:** the reviewed schemas are now deployed, durable in-app delivery is active, and the worker has executed successfully with email disabled. See [Approved deployment and actual delivery verification](notification-deployment-completion-report.md). The blocked state described below records the earlier pre-approval audit.

Implementation and automated verification are complete for the changes described below. Activation of the durable notification workflow is **blocked on approval for privileged schema deployment**. The main application account has no ALTER privilege. Reading the protected recovery administrator credential file was rejected by automatic approval review because that access was not explicitly authorized. No administrator credential was read, no privilege was granted, and no workaround was attempted.

The existing notice/payment flows remain compatible while the schema is pending. The new maintenance task exits successfully without modifying records until both reviewed schema additions exist. Durable cross-database alerts, reminder delivery and email retry are not claimed as active on the installed database yet.

## 1. Audit coverage and demonstrated gaps

Reviewed Xendit Session/Request/Payment/capture verification; callback authentication; duplicate, delayed and expired callbacks; isolated demo accounting; manual QR submission and Treasurer review; cash and waiver services; unified history; receipts/PDF generation; collection reports/summary; wallet authorization and simulation boundaries; role policies; audit hooks; bell/native and collection notice storage; mail configuration; queue configuration; maintenance scheduling; and account/data preservation.

Demonstrated gaps: the demo ledger did not generate bell notices; cross-database notification delivery had no durable event; manual submission did not acknowledge the submitting faculty member; notices lacked event-specific destinations; demo status pages did not poll; the bell did not refresh counts; due reminders and actual collection-target alerts were not integrated; history did not show payer identity; online-attempt oversight excluded the configured Administrator/President; and wallet routes were included twice. Existing provider-verification/capture checks and manual receipt uniqueness were retained.

## 2. Exact fixes and synchronization behavior

- Added a deduplicated demo notification outbox on the demo SQLite connection. A terminal event is recorded in the same transaction as its verified attempt. Delivery runs after source commit. Main notice insertion is transactional; source acknowledgment happens independently afterward. A failure between these commits is recoverable by replay without another alert. Terminal attempts can backfill events following deployment. No notification delivery exception reverses a verified demo transaction.
- Main `water_notices` records are the durable in-app/email delivery record. Additive fields store the unique event key, safe destination metadata, retry state, attempt count and timestamps. Existing notices/read states remain intact. Email delivery requires committed records and uses an atomic lease, bounded retries and sanitized logs.
- Faculty and authorized payment managers receive classified online-payment notices. All sandbox messages explicitly say simulated/no funds collected. Unchanged pending states do not generate repeated alerts. Expiry goes to the affected faculty member. Manual proof submission acknowledges the faculty member and alerts the Treasurer; approval/rejection supplies the correct owned details link, with a rejection/correction reason when applicable.
- Collection publication is deduplicated by member and published obligation details; changing an unpublished obligation can produce an accurate new publication message. Reminders run at configured day offsets `[3, 0, -3]`, only for active assigned members with unpaid/rejected published obligations. Each due-date/offset is unique. Pending, paid and waived members are excluded.
- Actual collection-target alerts count only QR/cash receipt amounts. Demo attempts and sandbox receipts do not contribute. These alerts explicitly do not establish provider-held/withdrawable funds. Normal provider withdrawals remain unavailable; no actual settlement/transfer was enabled or simulated as real.
- Added an authorized, read-only persisted demo-status endpoint. Status pages poll for up to five minutes, retry interrupted requests and stop at terminal state or timeout. GET/return endpoints never infer success or call a provider. The maintenance worker reads existing unresolved sessions to recover delayed/missing callbacks; it never creates checkout sessions.
- Added a private, authenticated bell feed, dynamic unread badge/list updates and bounded polling. Payment/history/dashboard views refresh when their authorized persisted-state revision changes, provided no form is being edited. Existing read, read-all, view-all, pagination, timestamps, empty states and ownership enforcement remain available. Old rendered state remains usable if the feed fails.
- Unified history includes payer identity and payer-aware search alongside existing date/method/status filters and pagination. Actual, isolated sandbox and demo ledgers remain financially separate. Historical records remain reachable within Transactions.
- Administrator/President may read online attempt details/status according to payments.view; POST reconciliation still requires ownership or payments.manage. Removed the duplicate wallet route include without changing destinations or permissions.

## 3. Automated end-to-end results

Full regression suite: **224 tests passed, 2,283 assertions**, no failures. After the final publication-key and recipient-permission refinements, the affected 60 tests also passed (640 assertions). JavaScript syntax checks passed; Blade compilation passed and compiled views were cleared. These are automated tests, not browser screenshots.

New focused tests exercise:

- Verified mocked online capture → one confirmed attempt → stable derived demo receipt → faculty/Treasurer history → classified faculty/Treasurer notices.
- Repeated webhook delivery → one logical source event and one notice per authorized recipient.
- Main notification failure after demo commit → payment remains confirmed → event remains pending → replay delivers exactly once; no genuine receipt or main obligation credit.
- Outer demo transaction rollback → neither completion nor notification event escapes.
- Owned read-only status polling and receipt URLs; another faculty account is forbidden.
- Manual submission → faculty/Treasurer acknowledgment → manual approval → one genuine receipt, completed history and actual target alert; repeated review remains blocked.
- Rejection → safe escaped reason, rejected member state and no receipt.
- Reminder repeat suppression, pending exclusion, unread/read count accuracy and permission revocation.
- Email failure → durable retry → fake successful transport acceptance → no extra send, receipt or payment mutation. No actual email was sent.
- Compatibility without privileged schema deployment and maintenance no-op while schema is absent.

Existing tests cover provider identity/amount/currency mismatches, invalid callbacks, capture evidence, unknown creation outcomes, interrupted requests, expired-session replacement, duplicate clicks, receipt/PDF authorization, reports, role restrictions, quota/accounting rules, simulated withdrawals, navigation and account/authentication regressions.

## 4. Installed database and real provider evidence

Read-only audit verified `ccis_local_20261018_164245` on port 3307, recovery mode zero. Hashes remained unchanged for all rows in eight protected tables: users, payments, water_collections, water_members, water_submissions, water_receipts, water_payment_accounts and audit_logs.

The installed main ledger contains **two actual QR/cash receipts totaling PHP 1,000.00**, with **zero member/submission/amount linkage issues**. The separate demo ledger contains **two confirmed attempts and one expired attempt**. Authenticated read-only Xendit verification of the two existing confirmed transactions passed the established Session/Request/Payment/capture validator and local reference/amount checks. No new external session was initiated; no payment was submitted or marked paid by this audit.

Demo receipts retain a stable `DEMO-<reference>` identity and are generated on demand. They are not genuine main-ledger receipt records. Tests confirm no main contribution, actual receipt total or real wallet credit is created by demo completion. The normal wallet simulation flag remains disabled.

Homepage and login returned HTTP 200 after the changes. Browser inventory failed with `The browser access host is unavailable`. Desktop/mobile visual layout, actual browser timing/interaction and PDF visual appearance therefore remain unverified in this run; existing receipt designs were retained.

## 5. Delivery infrastructure and deployment blocker

Brevo SMTP host and credential-presence checks passed, but `water.email_enabled` is false in the existing configuration. SMTP delivery/recipient acceptance was not tested and no bulk email was sent. Database-backed in-app delivery does not depend on email. SMTP cannot provide a strict exactly-once guarantee if a worker dies after transport acceptance but before the sent stamp; in-app delivery is deduplicated, while email has bounded recoverable at-least-once semantics.

Windows task **FacultyConnect Payment Synchronization** is registered at sign-in and every minute under Charles's interactive account, with overlapping runs ignored and a five-minute execution limit. Its guarded no-op completed with LastTaskResult 0 while schema deployment is pending. It does not run while Windows is off or before Charles signs in. Laravel's schedule also declares the maintenance command for environments with a scheduler runner.

Two reviewed additive migrations are prepared: main notice-delivery fields and the demo notification outbox. Only their explicit paths are used by `tools/install-payment-notifications.php`; it validates database identity, backs up SQLite and compares protected account/financial fingerprints. The main ALTER attempt was denied before any new column was applied. Both schemas are currently absent. See `payment-notification-schema-review.md` for review details. Approval to use the existing protected recovery administrator credentials solely for these migrations is required to finish activation.

A validated 24-table main SQL backup was created outside the web root: `20261118-150829-11918ab7.sql`, 121,238 bytes, SHA-256 `e3e5c66dbed9593c23147378d87b78833168265d5f9cb1fae8c1c2d7855b0e17`. The existing recurring-backup runner initially refused because only approximately 0.71 GiB was free. A temporary deployment-only copy used a 64 MiB floor for this small validated export. The original backup runner and its 1 GiB safeguard remain unchanged; recurring backups still need disk space restored above their threshold. Temporary backup diagnostics were removed.

## 6. Files modified or added

| Area | Files |
| --- | --- |
| Business events/delivery | `app/Services/PaymentNotifications.php`, `WaterPaymentService.php`, `PortalNotifications.php`, `PaymentHistory.php`, `Payments/XenditDemoService.php`, `Payments/WaterGatewayService.php` |
| Models/notification | `app/Models/WaterNotice.php`, `app/Notifications/WaterPaymentUpdated.php` |
| Controllers | `NotificationController.php`, `XenditDemoController.php`, `WaterGatewayController.php`, `WaterCollectionController.php` under `app/Http/Controllers` |
| Routes/settings | `routes/web.php`, `routes/water.php`, `routes/console.php`, `config/water.php` |
| Views | `resources/views/dashboard/partials/notification-menu.blade.php`, `notification-item.blade.php`, new `notification-list.blade.php`; `resources/views/water/demo/index.blade.php`, `show.blade.php` |
| Browser behavior/styles | `public/js/payment-notifications.js`, `public/js/water-online.js`, `public/css/payment-navigation.css` |
| Reviewed schema/tools | `database/migrations/2026_11_20_000000_add_payment_notification_delivery.php`, `database/demo/002_create_notification_outbox.php`, `tools/install-payment-notifications.php`, `tools/audit-payment-synchronization.php` |
| Tests | `tests/Feature/PaymentSynchronizationTest.php`, `XenditDemoTest.php`, `PayMongoGatewayTest.php` |
| Documentation | `docs/payment-notification-schema-review.md`, this report |

No APP_KEY, password, API key, TLS validation, webhook authentication, ngrok routing, role definitions, database connection, real withdrawal capability or existing financial record was changed. Source/provider failure logs exclude payloads and credentials. The remaining activation and verification limits above must be resolved before claiming the entire live workflow complete.
