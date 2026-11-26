# Normal FacultyConnect website and isolated test environments

Verified October 19, 2026, Asia/Manila. No migration, provider request, payment, tunnel activation, credential change or synthetic-data import was performed.

## URL map

| URL | Listener and backend | Actual database on port 3307 | Purpose |
| --- | --- | --- | --- |
| http://127.0.0.1:8000 | PHP PID 1860, normal Laravel server.php | ccis_local_20261018_164245 | Normal application: six existing users, two receipts |
| https://localhost:8443 | Node PID 4828 → PHP PID 2024, port 8002, tools/sandbox/router.php | ccis_xendit_test_20261018_111928 | Separate Xendit synthetic environment: three users, four receipts |
| https://localhost:8444 | Node PID 29252 → PHP PID 26124, port 8003, tools/wallet/router.php | ccis_wallet_test_20261018_175452 | Separate wallet simulation: three users, three receipts |

PIDs are observations, not permanent service identifiers. All three database identities were queried directly through their respective runtime bootstraps; recovery mode is zero. Both HTTPS bridges retain their separate protected environment files. Normal accounts are not expected to work in the synthetic databases and were not copied there.

Port 8444 is not the normal application. Its current trusted localhost certificate was validated using Windows curl with no bypass: HTTP 200, certificate verification result 0. Prior user-assisted Chrome confirmation remains documented in `wallet-localhost-https-trust-2026-10-19.md`.

## Normal website integration completed without schema changes

Sign in at **http://127.0.0.1:8000** using your existing account.

- Payments & Collections: `/dashboard/water`. The shared redesigned contribution, manual proof, review and receipt screens remain served by the normal app.
- Treasurer Wallet: `/dashboard/treasury`, now linked in the Payments & Collections navigation for authorized financial oversight roles.
- The normal wallet displays verified receipt totals, outstanding obligations, collection overview, method breakdown and linked receipt history using only the normal application's configured database.
- Historical payment records and collection reports remain linked separately; legacy payments are not added to receipt totals and double-counted.
- Provider available balance and settlement are explicitly **Not verified**, not zero or assumed withdrawable.
- Treasurer QR accounts and manual submission review remain linked for the Treasurer. Existing faculty payment/receipt authorization remains unchanged.
- Faculty cannot access treasury-wide data. Administrator has read-only oversight. All wallet mutation endpoints remain disabled on the normal environment. No simulated withdrawal controls or mock balances are displayed there.

The normal overview requires no wallet table. `wallet.enabled=false` selects this read-only view; it does not activate the simulation. The normal database still has no `wallet_accounts` table. The existing normal guard is `session-no-recaller`; APP_DEBUG=false, remote gateway disabled. Normal `.env` matches its preserved hash. Database credentials, APP_KEY, session settings, authentication guards, gateway implementations and ngrok files were not edited.

## Verification and limits

- **97 tests, 950 assertions passed** across normal overview, wallet, faculty authentication, Xendit, PayMongo, manual collection and payment suites. Tests run in guarded SQLite memory with fabricated accounts.
- Coverage includes normal overview without wallet schema, faculty denial, Admin/Treasurer visibility, blocked wallet mutations, QR account permissions, personal payment history and the existing login/security and payment regression cases.
- Normal HTTP login page returned 200; unauthenticated treasury access returned 302 to login.
- Read-only normal controller queries for existing Treasurer/Admin roles selected `wallet.overview` with the normal database's two receipts. These were in-memory controller/data checks, not real-account logins or authenticated HTTP sessions.
- A full-layout probe was stopped by MariaDB's read-only transaction safeguard (1792): the shared notification service attempts notification synchronization when rendering the dashboard. No write was permitted. Full HTML rendering is covered by synthetic automated tests instead; the production layout was not altered to bypass this behavior.
- Real passwords were not requested or used. Existing account login on port 8000 was previously user-confirmed; this phase does not claim a new live password/2FA test of each real account. Guest smoke requests may create ordinary unauthenticated sessions. No financial record was written.
- No synthetic users, receipts, payments, sessions or wallet balances were copied to the normal database. No service restart was needed.

## Still isolated / approval needed

Only port 8444 offers configurable targets, simulated settlement, reservations and simulated withdrawal outcomes. Port 8443 remains the prior Xendit test environment. These environments are not candidates for copying into the normal database.

To add editable quotas and a real accounting ledger to the normal app, request approval for a new, production-reviewed additive migration for `wallet_accounts`, `wallet_targets`, `wallet_destinations`, `wallet_settlements`, `wallet_withdrawals` and `wallet_entries`, plus any durable provider event/identity records established by the adapter review. The current experimental migration intentionally rejects normal databases and must not be run there.

Proposed rollout: verified backup and restore drill; approved schema in a disposable copy first; zero assumed provider balances; verified opening balances and allocation; audited destination enrollment; scoped authorization and reauthentication; reconciliation/concurrency tests; reviewed normal migration during a maintenance window. Preserve populated history with forward corrections rather than destructive rollback.

Provider processing is a separate approval boundary: verify the merchant's actual own-account withdrawal or payout capability, destination ownership, fees and permissions, then review a real adapter and its authenticated events/idempotency. No Money-out or real-contribution Money-in activation is included in the normal UI change. Until those approvals, the normal Treasurer Wallet remains an authorized read-only collection overview.

## Files changed

- `app/Http/Controllers/WalletController.php`: normal read-only branch using existing models; isolated simulation branch retained.
- `resources/views/wallet/overview.blade.php`: normal collection overview and receipt history.
- `resources/views/water/partials/navigation.blade.php`: normal financial-oversight wallet link.
- `tests/Feature/NormalTreasuryOverviewTest.php`; updated disabled-simulation expectations in `tests/Feature/TreasurerWalletTest.php`.
- `tools/wallet/environment-audit.php`: CLI-only, metadata-only runtime identity and read-only controller-data verification.
- This report.
