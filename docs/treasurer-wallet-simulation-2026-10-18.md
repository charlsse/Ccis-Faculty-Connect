# Treasurer Wallet — isolated simulation delivered

Implemented October 18, 2026. **No real withdrawal or payout capability is enabled.** The existing Money-in gateways, normal `.env`, authentication configuration, API keys and ngrok configuration were not changed. No provider API request was made for this work.

## What is available

The new Treasurer Wallet combines verified receipt totals, collection targets, outstanding contributions, simulated available and pending funds, reservations, fees, withdrawal history, method breakdown and recent ledger activity. It links back to the existing contribution and receipt screens. Administrator/President has read-only access; faculty retains its existing contribution history and cannot access treasury controls.

Treasurers can adjust a collection target, select an eligible collection and fixed synthetic destination, review an amount, reauthenticate with their password, and reserve a simulated withdrawal. The review expires after five minutes. Amount, collection and destination are retained in the server-side review; confirmation does not accept replacement recipient or amount fields. An altered destination version invalidates the review. Existing login, 2FA, account usability and CSRF protections remain in force, with additional route throttles.

Simulation controls demonstrate pending → processing → completed, pending/processing → failed, and completed → reversed. Duplicate outcomes do not repeat ledger changes. Conflicting late outcomes are rejected. These are authenticated local simulator controls, **not public provider webhooks**.

## Separate environment and access

| Item | Value |
| --- | --- |
| Protected root | `C:/Users/Charles/FacultyConnect-Sandbox/wallet-20261018` |
| Database | `ccis_wallet_test_20261018_175452` |
| MariaDB | 127.0.0.1:3307, 10.4.32, recovery mode 0 |
| Backend | 127.0.0.1:8003 |
| Browser HTTPS | https://localhost:8444/dashboard/treasury |
| Runtime account | `fc_wt_1018175452`, scoped SELECT/INSERT/UPDATE/DELETE |
| Migration account | Separate scoped account; not used by the HTTP process |
| Feature flag | `WALLET_SIMULATION_ENABLED=true` only in new private configuration |
| Public tunnel / provider calls | Disabled; no credentials configured for provider access |

The normal feature defaults to disabled. The wallet service and opt-in migration reject any database outside the `ccis_wallet_test_*` loopback identity, except the existing guarded SQLite in-memory test environment. The new bootstrap rejects active payment/tunnel/remote-approval flags and blocks Laravel HTTP-client stray requests. The simulator has no network client. All three activation flags remain false.

The protected directory permits Charles, SYSTEM and Administrators. `runtime.env`, `migration.env` and `test-accounts.private.json` contain new synthetic credentials. Open the account file locally to obtain the synthetic login and TOTP setup; do not attach it or runtime configuration to chat. Only fabricated data was seeded: three users, three collections, three approved contributions/receipts and one outstanding assignment. No real faculty data, trusted devices, audit records, proofs or sessions were copied.

The new processes are loopback-only and remain running for local review. Restart after a reboot, if necessary, with:

```powershell
& 'C:\xampp\htdocs\CCIS_CONNECT\tools\wallet\Start-WalletLocal.ps1' -PrivateDirectory 'C:\Users\Charles\FacultyConnect-Sandbox\wallet-20261018'
```

The helper refuses occupied ports and does not restart existing services. The localhost bridge requires its private header to reach the backend, strips forwarded headers, enforces the localhost host, and rejects private file paths and provider callback paths. It does not expose port 8000 or change the existing Xendit environment on 8002/8443.

### New localhost certificate

Automated HTTPS checks verified the certificate and hostname using the explicit certificate as a trust anchor; verification was never disabled. Windows browser trust was **not** changed automatically. The certificate expires November 17, 2026, 15:56:26 UTC. SHA-256:

`5a2fbfafd72d708ec6e2d6923af011e42f8d949deec0b44b02f6762410ed50fd`

For browser review, inspect `localhost.crt` in the new protected root and compare this fingerprint using `openssl x509 -in <certificate> -noout -fingerprint -sha256`. After confirming it is this localhost-only certificate, import only the `.crt` into the **current user's** Trusted Root Certification Authorities store using Windows Certificate Import Wizard. Never import or share `localhost.key`, disable TLS verification, or change Secure/HttpOnly cookies to bypass a trust warning. Remove this specific certificate from trust when retiring the simulation. No existing localhost certificate was replaced.

## Accounting model

Six additive tables reside only in the new schema: `wallet_accounts`, `wallet_targets`, `wallet_destinations`, `wallet_settlements`, `wallet_withdrawals`, `wallet_entries`.

- **Contributions:** existing verified receipts are the canonical historical collection total. Gateway attempts are not added again. Withdrawal activity never creates a faculty submission/receipt or reduces historical collections.
- **Manual funds:** cash and manual QR receipts remain outside the simulated provider wallet. They can contribute to a collection's historical target, but cannot supply provider available funds.
- **Settlement:** a unique receipt linkage records gross, fee and pending net amount. An explicit simulated settlement moves net from pending to available. Confirmed contribution alone is not available money. The demo uses fabricated Xendit TEST receipts, not the previous successful provider transaction.
- **Reservation:** withdrawal amount plus quoted simulation fee moves from available to reserved atomically. Both collection-specific and overall balances are checked after locking the account row. Competing processes cannot overspend the same balance.
- **Completion/failure/reversal:** completion moves reserved principal to withdrawn and records any fee; failure releases the reservation; reversal returns principal and fee under the documented simulation policy. Real-provider fee refund rules remain unverified. The mock transfer fee is zero; seeded settlement fees are fabricated PHP 1.00 per online receipt. Neither is a Xendit price quote.
- **Ledger and reconciliation:** unique event keys prevent duplicate entries; the application has no ledger edit/delete endpoint. Reconciliation independently derives balances from settlements and withdrawal states and compares them to ledger sums. A mismatch blocks new requests. Reversals are compensating entries, not historical deletion. Arbitrary balance adjustments and live refunds are not implemented.

Collection targets are local FacultyConnect rules, stored separately per collection/billing period. Reaching a target does not override settled-balance, destination, role, authentication or reconciliation checks.

The normalized `TransferProvider` boundary accepts mocked results in this phase. It validates simulation mode, reference, PHP amount, destination version and outcome. `TransferSimulator` is the only implemented provider. The fixture uses documented external-payout status vocabulary (`SUCCEEDED`, `FAILED`, `REVERSED`) for mapping tests; it is deliberately not presented as a complete merchant-withdrawal payload or an implemented Xendit payout adapter.

## Verification results

| Verification | Result |
| --- | --- |
| Wallet + Xendit + PayMongo + manual collections + permissions suites | **89 tests, 890 assertions passed**, SQLite in memory |
| Isolated MariaDB validation | **76 checks passed** |
| Real concurrent PHP processes | Two PHP 70.00 requests against PHP 99.00 available: one reserved, one rejected |
| Local HTTPS flow | **36 checks passed**, TLS verification enabled |
| Final index scans | **103 passed** across all 30 tables |
| Foreign keys and extended table checks | Passed in MariaDB validation |
| Rollback guard | Refused rollback when accounting history exists |
| Final accounting reconciliation | Matched |

Coverage includes disabled feature, Treasurer-only mutations, Admin/President read-only access, faculty history, receipt/manual-fund separation, quota and pending-fund rejection, duplicate/conflicting requests, reservations, completion, failure, reversal, ambiguous provider results, changed destination, expired review, incorrect password, tampered confirmation fields and reconciliation mismatch. Existing Money-in capture/webhook/idempotency tests and manual-payment regressions pass unchanged.

HTTPS tests used real requests with new synthetic accounts: login, TOTP, role access, contribution views, wallet rendering, review, password confirmation, duplicate confirmation, simulation outcomes and logout. Secure, HttpOnly and SameSite=Lax cookies were verified. The test did not use any real faculty account. Browser layout has not been visually reviewed; responsive HTML/CSS and server-rendered views were exercised, not a visual screenshot audit.

Final demo balances: PHP **89.00 available**, **79.00 pending**, **0 reserved**, **10.00 net completed transfers**, **2.00 simulated settlement fees**. Historical verified contributions remain **PHP 230.00**, including PHP 50.00 manual cash. Exactly three original synthetic submissions and three receipts remain. History contains one failed, one reversed and one completed simulated withdrawal; these are intentional demo/test artifacts, not real transactions.

## Preservation and evidence

Read-only comparisons found all 23 non-session tables unchanged in each of `ccis_local_20261018_164245`, `ccis_connect_verified` and `ccis_candidate_20261018`; normal `.env` matches its baseline hash. The original damaged database was not targeted. Existing recovery archives and the successful Xendit trial environment were not modified by these scripts. No ngrok/cloudflared process was found at verification.

New protected evidence: `identity.json`, `fixtures.json`, `validation.json`, `https-validation.json`, `final-check.json`, `preservation-before.json`, `preservation-after.json`, private runtime/process logs and rendered HTML. One early HTTPS check found that unknown private paths did not return the intended explicit denial; the wallet-only router was corrected and all 36 checks passed afterward. This did not expose a configuration file.

Synthetic snapshot: `wallet-20261018-155952-cba4f4.sql`, exit 0, completion marker and all 30 table definitions verified. SHA-256:

`ed611f62e69ccf0fee52976b141953727c9f27788153c92a5a640af9290b90b4`

This snapshot has not been separately restore-tested; it excludes triggers, events and routines. Do not use it as a normal FacultyConnect restore source. New preparation and concurrency/HTTP validation scripts intentionally create synthetic history and are **not** safe to rerun blindly on this demo. Existing recurring backups for the recovered application are unchanged.

## Xendit feasibility and approval boundary

Xendit documents merchant-balance withdrawal from its Dashboard to registered accounts, with a PIN and separate manual/automatic options. That is distinct from sending an external payout. The reviewed documentation does **not** establish a merchant-withdrawal API available to this account. [Withdraw balance](https://docs.xendit.co/docs/withdrawal).

`GET /balance` reports a selected cash/holding account, supports PHP and uses secret-key Basic authentication. It is account-level money, not a collection-specific ledger; any future adapter must reconcile allocation, reservations and other merchant activity. No balance lookup was performed. [Get balance](https://docs.xendit.co/apidocs/get-balance).

The documented external-payout endpoint is `POST /v3/payouts`, API version `2025-09-01`, requiring MONEY-OUT permission. Its business-scoped idempotency key returns the original result for the same request and rejects a changed body. None of these permissions or requests was enabled. [Create Payout](https://docs.xendit.co/apidocs/create-payout-v3).

Philippine payout coverage includes PHP destinations over InstaPay/PESONet; supported banks/wallets, limits, downtime and cutoffs must be confirmed for the actual channel and account. Do not treat a listed channel as proof of this merchant's access. [Philippines coverage](https://docs.xendit.co/docs/payouts-coverage-philippines).

Money-in settlement timing varies by payment channel and can take several business days. Merchant fee schedules, taxes, withdrawal/payout charges and returned-fee rules require account-specific confirmation; the public pricing page is not proof of the user's contracted pricing. [Settlement](https://docs.xendit.co/docs/settlements-overview), [Pricing](https://www.xendit.co/en/pricing/).

External-payout notifications include success, failure, reversal, rejection and pending compliance. Xendit's general webhook guidance specifies `x-callback-token` authentication, duplicate protection and no guaranteed event ordering. A future adapter needs a durable authenticated inbox, fresh authoritative payout verification, ambiguity handling, and reconciliation rather than treating every callback as a safe transfer instruction. This simulation exposes no payout webhook. [Payout Webhook](https://docs.xendit.co/apidocs/payout-v3-webhook), [Handling webhooks](https://docs.xendit.co/docs/handling-webhooks).

**Unknown locally:** merchant product activation/KYC, authorized Treasurer ownership of the destination, ability to programmatically withdraw merchant funds, Money-out access, balance permissions, exact PHP channels/limits, fees and settlement schedules. No existing secret key was inspected or changed to infer these capabilities.

## Proposed normal rollout — not authorized or performed

1. Confirm with Xendit which product implements the intended own-account transfer, its account eligibility, destination ownership, permissions, fee rules, idempotency and status/reversal contract. Do not substitute external payouts without an explicit product decision.
2. Review a separate real adapter and security design: isolated credentials, approved immutable destination enrollment/change controls, balance freshness, authoritative provider verification, durable outbox/inbox, retries without duplicate creation, and operator handling of ambiguous results. Keep Admin/President view-only and Treasurer step-up authorization.
3. Obtain approval for a normal-schema migration plan. The present migration deliberately refuses normal databases. A production version would create the six new tables without changing existing contribution tables, add audited target/destination management and durable provider identity/event tables, and establish a verified opening balance/allocation. Never backfill available money from receipt totals alone.
4. Take and restore-test a fresh protected normal backup; test the approved migration and reconciliation in a disposable copy first. Schedule a maintenance window. No migrate:fresh/reset or blanket rollback. Populated ledger history must be preserved with forward corrections.
5. Validate an independently approved provider Test Mode integration, failures/reversals, concurrency, destination protections, UI layout/accessibility, backup coverage and restore drills. Keep the normal feature off until approval. Do not promote this synthetic database or its accounts/history.
6. Any live credentials, Money-out permissions, public endpoints or real transfers require a separate explicit decision and limited rollout. Rollback should disable new requests, retain all accounting/event evidence and reconcile in-flight transfers; never discard financial history or revert to the corrupted original database.

## Changed files

- `config/wallet.php`; `routes/wallet.php`; one include in `routes/web.php`.
- `app/Http/Controllers/WalletController.php`.
- `app/Services/Wallet/SimulationBoundary.php`, `WalletService.php`, `TransferProvider.php`, `TransferSimulator.php`, `SyntheticFixtures.php`.
- `database/experimental/wallet/2026_10_18_230000_create_simulated_wallet.php` (opt-in; only applied to new synthetic schema).
- `resources/views/wallet/index.blade.php`, `review.blade.php`; conditional simulation link in `resources/views/water/partials/navigation.blade.php`; `public/css/wallet.css`.
- `tests/Feature/TreasurerWalletTest.php`; `tests/Fixtures/xendit-payout-statuses.json`.
- `tools/wallet/provision.php`, `prepare.php`, `bootstrap.php`, `router.php`, `local-https.cjs`, `Start-WalletLocal.ps1`, `concurrent-worker.php`, `validate.php`, `https-validation.php`, `final-check.php`, `snapshot.php`.
- This report; newly created protected synthetic environment/evidence described above.

No existing payment gateway, authentication source/configuration, normal database connection, API key, ngrok configuration or real financial record was changed. Real withdrawal functionality is **not operational**.
