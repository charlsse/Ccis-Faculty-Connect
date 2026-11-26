# Unified FacultyConnect integration — code ready, protected rollout pending

October 19, 2026, Asia/Manila. The normal website remains **http://127.0.0.1:8000**, using **ccis_local_20261018_164245 at 127.0.0.1:3307**. No user-facing redirect to 8443 or 8444 was added. This phase implements the authorized shared-code work and validates an additive migration in a disposable copy. **Normal schema rollout and real online payments/withdrawals are not complete or activated.**

## Dependency assessment and implementation

The application already shares its contribution, QR submission, approval, receipt/PDF, payment-history and report controllers across environments. The Xendit adapter already performs Session → Payment Request → Payment verification, capture checks and duplicate-safe confirmation. It is deliberately restricted to Test Mode and an isolated test database. A UI change or flag alone cannot safely convert it into a normal real-money gateway.

The synthetic wallet service also deliberately refuses the normal database. Its simulator and mock ledger were not copied into normal operation. The normal wallet now uses the same website navigation and existing authoritative records, with inactive provider functions clearly distinguished.

Changes in this phase:

- The checkout promotes **Xendit hosted TEST checkout only when the existing adapter reports availability and the signed-in faculty role is eligible**. Manual Treasurer QR remains available as an alternative. With the normal gateway disabled, Pay Now continues to use the Treasurer QR flow and explains that online activation is pending.
- The normal wallet has payment-method and collection filters, pagination, verified receipt links, collection totals, outstanding obligations and method breakdown. Summary totals stay unfiltered and are not multiplied by attempts or submissions.
- Contribution reconciliation checks receipt amount/member linkage against approved submissions and paid members. It is explicitly distinct from provider-balance reconciliation.
- Prepared quota forms show verified collection progress against a stored target, where the reviewed schema exists. Without that schema, normal users see a pending-setup state. Targets are FacultyConnect rules, not provider rules.
- `CollectionTargets` authorizes only active Treasurers with payment-management permission. Updates lock the collection, reject stale revisions, preserve a unique operation key and write an audit in the same transaction. Same-request retries are idempotent; conflicting requests fail.
- The withdrawal request control is visibly disabled with its reason. No simulated destination, success, balance or history is presented as real. Provider cash balance and settlement remain “Not verified.”
- Responsive layout rules, focus indicators, reduced-motion handling and compact filters were added only to the payment/wallet screens. No unrelated modules were redesigned.

Existing server-authoritative amounts, receipt snapshots/PDFs, role policies, manual QR processing, callback-token validation, capture verification, unique references and idempotency were retained. No gateway service, authentication guard, mandatory 2FA, trusted-device setting, normal database connection, APP_KEY, existing API secret or ngrok configuration was edited.

## What works on port 8000 now

- Existing account login flow and security settings remain in place.
- Faculty contribution checkout, Treasurer QR/manual-proof workflow, payment status and receipt/PDF access use the normal database.
- Existing historical payments and financial reports remain accessible under their existing permissions.
- Treasurer/Admin financial oversight includes the unified wallet overview, receipt filters/history and contribution reconciliation.
- Faculty cannot view treasury-wide data; Admin/President does not gain financial mutation permissions. The configured Administrator role represents the existing President/Administrator oversight policy; no new role was introduced.
- No external provider balance, settlement or withdrawal capability is represented as operational.

All actual normal accounts and existing records were preserved. There are six users: one active Admin, three active faculty, one active Treasurer and one Treasurer pending activation. That pre-existing pending state was preserved, not bypassed or automatically activated. A test probe that selected that pending copied Treasurer was correctly denied; subsequent write-producing tests used a designated disposable Treasurer instead.

## Reviewed additive migration — NOT applied to normal

File: `database/reviewed/wallet/2026_10_19_010000_create_treasury_accounting.php`.

It is outside the default migration directory and refuses unapproved target identities. The process-level `FC_WALLET_SCHEMA_APPROVED_DB` must match the actual database; allowed targets are the named normal recovered database or a `ccis_unified_review_*` copy on loopback port 3307, recovery mode zero. No such approval was supplied for normal. Guarded SQLite-memory tests are also supported.

| New table | Purpose |
| --- | --- |
| wallet_accounts | Unverified provider/account identity; no seeded cash balance |
| wallet_targets | Per-collection quota, revision and updating officer |
| wallet_destinations | Versioned destination metadata; initially unverified |
| wallet_settlements | Provider settlement references and gross/fee/net evidence |
| wallet_withdrawals | Unique request/idempotency references and destination snapshot |
| wallet_entries | Append-only application ledger structure, including fees/adjustments |
| wallet_audits | Unique audited operations with before/after target state |
| wallet_provider_events | Unique authenticated event identities and protected payload structure |
| wallet_balance_observations | Timestamped provider balance evidence, separate from receipts |

The migration creates **empty structures only**. It does not alter existing contribution/account tables, import synthetic history, seed a wallet account, credit balances or enable transfers. It rejects any existing/partial experimental wallet schema instead of trying to merge it. Restrictive foreign keys and unique references protect linkage. Rollback works while all new tables are empty and refuses any populated target, audit or financial history. Production recovery must use forward corrections once data exists.

The schema prepares provider integration but does not itself implement an authoritative provider ledger writer or Money-out adapter. Those services require separate contract/security review and approval. An observed provider balance would need freshness, identity, reservation and allocation reconciliation before it could authorize a transfer.

## Protected disposable copy and migration validation

Protected root: `C:/Users/Charles/FacultyConnect-Integration/review-20261019-b`.

Database: **ccis_unified_review_20261019_004918**, MariaDB 10.4.32 on port 3307. The copy uses scoped runtime and migration accounts. Its runtime account was directly denied access to normal users. No additional public or user-facing website was created; tests use the existing Laravel code through the private CLI bootstrap.

A fresh export of **23 non-session source tables / 298 source rows** was restored into the copy. Full row hashes matched both the pre-export source snapshot and the source immediately after export. A new empty sessions table uses the source schema; active login sessions were not copied. APP_KEY was retained privately in the review configuration to read copied encrypted fields; the original key/configuration was not changed. No proof uploads or real QR image files were copied.

Source SQL SHA-256:

`5aca663aeb33170daf1abac56cc082518974f430c7a4056887aaa47c9cddaa19`

This table/row export was restore-tested; it excludes sessions, triggers, events and routines and is not a full server backup. The directory contains sensitive copied records/configuration and restricts access to Charles, SYSTEM and Administrators. Do not publish it or promote it into production.

The initial copy attempt under `review-20261019` encountered import error 1044 with the scoped restore account. That partial copy/export was retained. A fresh export without embedded lock statements restored successfully into a new database. No original schema was overwritten, and privileges on the normal app account were not broadened.

The successful copy retains one clearly designated disposable concurrency-test Treasurer, one quota and one audit as evidence. The other test accounts and financial effects were transaction-rolled-back. All original copied rows remain unchanged. This copy is test evidence, not a cutover source.

## Test outcomes

| Test layer | Result |
| --- | --- |
| Automated shared-code regressions | **102 tests / 983 assertions passed**, guarded SQLite memory |
| Disposable MariaDB copy | **106 checks passed** |
| Additional copied-data manual QR flow | **7 checks passed**, all data changes rolled back |
| Final index scans | **113 passed** |
| Foreign keys / extended table checks | Passed across the copied and added tables |
| Concurrent quota updates | One saved, one rejected as stale; exactly one audit |
| Empty migration rollback | Passed; existing application tables retained |
| Populated migration rollback | Correctly refused |
| Normal preservation | All 23 non-session table hashes / 298 source rows unchanged; `.env` hash unchanged |

Automated HTTP tests cover login/2FA, faculty access, Admin oversight, Treasurer permissions, manual QR/proof validation, receipt/PDF authorization, history, quotas, disabled withdrawal routes, Xendit/PayMongo confirmation, duplicate and failed payment cases, and conditional Xendit checkout presentation. Provider calls in these suites are mocked.

The MariaDB copy exercised password and 2FA controller flows using temporary test accounts, full payment/wallet/report rendering, manual cash receipt generation, duplicate rejection and PDF output. Notification synchronization remained confined to the copy and was rolled back. Copied original password/security records were not changed. These controller/service tests are not claimed to be real-account browser sign-ins.

The additional QR service test submitted proof metadata, observed pending status, approved it as a disposable Treasurer, verified one receipt, rejected a duplicate approval, found the receipt in treasury history, checked reconciliation and generated a PDF. All seven checks passed and the copy's prior records were restored by rollback. Actual upload validation and HTTP permissions were covered separately by the automated HTTP suite; no real bank transfer was made.

Normal live smoke checks: `/login` returned 200; a guest request to `/dashboard/treasury` redirected to login. Normal read-only controller-data probes succeeded with existing authorized roles and the two normal receipts. Full normal authenticated browser testing was not performed with real credentials. No real account was used for write-producing tests.

HTTPS validation of the existing localhost:8444 infrastructure returned 200 with certificate verification 0; Secure/HttpOnly/SameSite controls and prior HTTPS tests remain unchanged. Port 8000 is still intentionally HTTP; it has not been relabelled as HTTPS or moved to another environment.

Browser automation initialization still fails (`failed to write kernel assets ... os error 3`). Responsive CSS/HTML is implemented and render-tested, but mobile visual layout and interactive browser review remain unverified. This is a remaining testing requirement, not a claimed pass.

## Differences and remaining blockers

1. Normal has no wallet schema. Quota editing remains unavailable until the reviewed migration is approved and applied.
2. Normal remote payments remain disabled. The existing gateway is sandbox-only and correctly refuses real normal contributions. A real-money adapter/configuration rollout, supported callbacks, HTTPS/public exposure and provider account readiness require a separate proposal and approval. No test key/session may settle a real contribution.
3. Real withdrawals remain unavailable. Verify whether Xendit supports the intended own-account withdrawal or an explicitly chosen external-payout product, eligible destinations, fees, settlement rules and permissions. Do not infer eligibility from the prior successful Money-in test.
4. The reviewed provider ledger/event tables have no authoritative integration writer yet. They remain empty; receipt totals are not converted into available funds.
5. Complete mobile/browser review and user-assisted existing-account acceptance checks before declaring the whole unified rollout complete.

Ports 8443 and 8444 remain developer-only test infrastructure with their own databases and accounts. Normal users need neither port. The successful prior Xendit trial, capture correction and recovery archives were not modified.

## Exact approval requested next

**Approve only the normal additive schema/quota rollout**, if desired:

1. Agree a short maintenance window and quiesce schema writers.
2. Take a new protected backup of the current normal database and restore-verify it; do not reuse the review copy as a source.
3. Reconfirm the exact database/port and apply only the reviewed nine-table migration using a privately authorized scoped migration connection. Do not run the experimental migration, migrate:fresh/reset or unrelated pending migrations. The normal runtime credentials remain unchanged; no `.env` edit is required.
4. Verify source-row hashes, foreign keys, indexes and empty provider/withdrawal structures. Record the Laravel migration normally.
5. Enable only the schema-dependent audited Treasurer quota controls through the already-integrated code. Admin remains view-only. Keep gateway, tunnel and remote-approval flags unchanged/disabled; leave provider balances unknown and withdrawals disabled.
6. Validate the normal page and permissions, inspect logs, and update recurring backup expectations for the additional tables. If a failure occurs before any new data is recorded, the reviewed empty-table rollback is available. Once targets/audits exist, preserve them and use a forward fix; never drop populated accounting history.

This approval would **not** authorize Xendit activation, Money-out permissions, new provider sessions, public tunnels, environment changes or real transfers. Those remain separate decisions. Stopped here before the requested protected operations.

## Files changed

- `app/Http/Controllers/WalletController.php`: receipt filters, contribution reconciliation, optional quota view data and audited normal target branch.
- `app/Services/Wallet/CollectionTargets.php`: role/schema guards, target locking, stale-update protection and audit/idempotency.
- `resources/views/water/checkout.blade.php`: conditional primary Xendit TEST action, preserved QR alternative and unavailable state.
- `resources/views/wallet/overview.blade.php`; new `partials/quota.blade.php`, `partials/history-filter.blade.php`.
- `public/css/unified-payments.css`.
- `database/reviewed/wallet/2026_10_19_010000_create_treasury_accounting.php`.
- `tests/Feature/ReviewedTreasurySchemaTest.php`; additional checkout presentation regression in `XenditGatewayTest.php`.
- `tools/unified/prepare-copy.php`, `bootstrap.php`, `validate-copy.php`, `quota-worker.php`, `verify-manual-qr.php`, `final-check.php`.
- This report and the newly created protected review artifacts.

No existing gateway/authentication implementation, normal `.env`, database connection, session setting, API secret or ngrok configuration was changed. No normal migration, live payment, real withdrawal or public tunnel activation occurred.
