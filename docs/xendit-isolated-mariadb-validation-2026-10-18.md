# Xendit isolated MariaDB validation — October 18, 2026

## Outcome and approval boundary

The synthetic MariaDB integration checks passed. ngrok is installed and its prepared configuration passes offline validation. A separate localhost HTTPS application passed authentication, session and permission checks without disabling certificate verification.

**No public tunnel was started, public endpoint registered, Xendit API called, or provider Test Mode payment performed.** Mocked provider calls were used for every payment integration test. The synthetic runtime has `PAYMENT_GATEWAY_ENABLED=false`, `TUNNEL_ENABLED=false`, `FC_SANDBOX_REMOTE_APPROVED=false`, and no Xendit key. Unmocked Laravel HTTP calls are blocked. The normal recovered application remains on port 8000.

The next step requires your explicit approval for public activation and one controlled remote Test Mode trial after the private setup checklist is complete. See [credential setup and first-trial procedure](xendit-sandbox-trial-checklist.md).

## Synthetic database

| Setting | Verified value |
| --- | --- |
| Database | `ccis_xendit_test_20261018_111928` |
| Server | MariaDB 10.4.32, 127.0.0.1:3307, recovery mode 0 |
| Runtime account | `fc_xt_1018111928@127.0.0.1` |
| Migration account | `fc_xm_1018111928@127.0.0.1` |
| Application tables | 25, including sessions and gateway attempts |
| Users | Three explicitly synthetic accounts: faculty, Administrator/President, Treasurer |
| Collections / assigned contributions | Six / six, PHP 10.00 each |
| Imported source data | None |

The schema was built from the 14 existing base migrations and the opt-in Xendit migration, not a recovery export. New accounts use `example.invalid` addresses, generated passwords/TOTP secrets, a fresh APP_KEY, and new sessions. No real faculty details, credentials, trusted devices, proofs, audit rows or session contents were copied. Application-created audit/session rows are synthetic test effects. The future trial collection remains unpaid with no gateway attempt.

The runtime account has only SELECT/INSERT/UPDATE/DELETE on the literal synthetic database. The separate migration account has the additional DDL permissions needed only on that database. Underscores are escaped in grants. Direct runtime reads against recovered, baseline, earlier candidate and original-named schemas were denied; runtime CREATE TABLE was denied.

## MariaDB migration, integrity and concurrency results

- All **25 tables** passed full reads and extended table checks.
- **86 index scans** passed.
- **26 declared foreign-key relationships** had zero orphan rows.
- An actual duplicate active-member reservation insert was rejected by its unique index (1062).
- An actual nonexistent-member insert was rejected by its foreign key (1452).
- The empty gateway migration rolled back successfully and was reapplied before seeding.
- Rollback with payment-attempt history was refused; all history remained present. No unrelated/base migration rollback was attempted.
- Two simultaneous creation workers produced **one attempt and one outbound mock creation** for their contribution.
- Four simultaneous confirmation workers produced **one approved submission and one receipt**.
- Concurrent online confirmation and Treasurer cash recording produced **one receipt**; cash was safely rejected because an online reservation existed.
- A canceled session remained reserved and unpaid. An ambiguous/canceled provider state never enabled a second payment.
- The synthetic faculty submitted a manual reference, the Treasurer approved it, and the normal manual receipt was generated. Administrator/President financial write access was denied while oversight was allowed.

Workers ran in separate PHP processes/connections with a shared start barrier. These are actual MariaDB locking/constraint tests with mocked provider responses, not a sustained load test or remote Xendit trial. Manual proof metadata was synthetic; no real proof file was imported.

Final fixture evidence: 3 users, 6 collections, 6 contributions, 4 gateway attempts, 3 submissions and 3 receipts (two verified mock online payments and one Treasurer-reviewed manual payment), 14 synthetic audit rows, 7 synthetic session rows, and zero trusted devices. Session counts may change during local use. Existing historical `payments` remains empty in the synthetic schema.

## API compatibility review and fixes

Rechecked the official [Create Session](https://docs.xendit.co/apidocs/create-session), [Get Session](https://docs.xendit.co/apidocs/get-session), [Get Payment Request](https://docs.xendit.co/apidocs/get-payment-request), and [session webhook](https://docs.xendit.co/apidocs/webhook-notification-sent-defined-webhook-url-updates-payment-session) documentation. The adapter continues to use hosted PAYMENT_LINK/PAY sessions, PHP major currency units, PH, automatic capture, no saved payment method, synthetic customer data, and HTTPS return URLs. Payment Request verification uses API version `2024-11-11`.

Webhook authenticity uses the documented **x-callback-token** mechanism, with business/session/reference/amount checks followed by authenticated server-side verification. The provider's [verification-token guidance](https://help.xendit.co/hc/en-us/articles/360038072991-How-to-validate-if-the-webhook-is-sent-from-Xendit) supports this mechanism; no invented HMAC scheme or browser-return confirmation is used.

This phase tightened two cases:

1. A completed session must carry a captured `payment_id` matching the successful Payment Request's latest payment identity. Missing or inconsistent identities cannot settle a contribution.
2. An expired session carrying any captured-payment reference cannot release its contribution, even if its Payment Request link is absent. Expired linked requests also require matching country and PAY type before release is considered.

Canceled, authorized-but-uncaptured, failed, pending and ambiguous states stay unpaid/reserved. Expiration is only released when verified safe. Duplicate and late events cannot produce extra receipts. The session's reference is matched to the local attempt; the linked Payment Request ID, business, amount/currency, country, payment type, capture method, status, payment ID and captured total are independently verified. Browser redirects remain read-only.

Official sample payloads are not a substitute for a real contract test: some sample linked IDs differ in shape from the Payment Request endpoint's documented ID constraints. The adapter follows endpoint constraints and fails closed on unexpected responses. Actual merchant permissions, hosted channels and callback/capture fields still need the approved Test Mode trial.

## Automated and HTTPS results

**71 PHPUnit tests / 696 assertions passed**, covering Xendit, PayMongo, manual collections and historical payments. This includes 18 Xendit/tunnel/schema tests. New cases cover missing captured identity, canceled/authorized outcomes, and expired sessions that still reference captured payments. All new PHP helpers passed syntax checks; the Node HTTPS proxy passed its syntax check.

**33 actual HTTPS checks passed** against the synthetic runtime:

- TLS certificate chain/pin and localhost hostname verified; verification was never disabled.
- Separate session cookie with Secure, HttpOnly and SameSite=Lax.
- Password login, TOTP challenge, valid TOTP, dashboard, security, collections, report permissions, Treasurer account-management permissions and logout for all three synthetic roles.
- Faculty report access denied; Administrator/President and Treasurer report access allowed; only Treasurer could access account management.

The browser certificate trust step remains pending. Automated clients used the exact generated public certificate as their trust anchor. Do not bypass browser certificate warnings or weaken session cookies.

## Local runtime and ngrok

| Surface | Address / status |
| --- | --- |
| Normal recovered application | `http://127.0.0.1:8000`, unchanged |
| Synthetic PHP backend | `http://127.0.0.1:8002`, loopback only, custom restrictive router |
| Synthetic browser origin | `https://localhost:8443`, loopback only |
| ngrok | Installed from Microsoft Store, `3.39.9-msix-stable`; not running |
| Prepared ngrok upstream | Only port 8002, never port 8000 |
| Public hostname | Unconfigured; `https://pending.invalid` is a deliberate placeholder |

The HTTPS proxy forwards to the synthetic backend with a private random local transport token. It validates Host, strips forwarded headers and sets no public listener. The backend trusts local HTTPS only with that token and exact localhost Host. Public-mode traffic takes a separate path and must meet the public host/method/path restrictions. Ordinary direct backend requests are denied.

Prepared `ngrok.yml` passes **`ngrok config check`**. The actual embedded traffic policy matches `tools/ngrok-xendit-policy.yml` and permits only:

- POST `/webhooks/payments/xendit`
- GET `/payments/xendit/return`

Other requests terminate at the edge with 404, including static files. The Laravel middleware and custom router independently restrict public access. Seven local public-style requests to login, dashboard, health, `.env`, storage and the inactive callback/return paths all returned 404. Active middleware host/HTTPS/path rules also passed automated tests.

The [ngrok v3 configuration](https://ngrok.com/docs/gateway/agent/config/v3) uses an inline policy map, `web_addr: false`, `inspect_db_size: -1`, disabled remote management, and warning-level logs. `-1` disables local inspection; `0` is the default 50 MB allocation, not “off.” No auth token was supplied. No cloud endpoint was registered. Offline configuration/rule validation does **not** claim execution of the ngrok edge engine; public negative-path tests must be repeated immediately after approved activation, before payment creation.

Local processes were left available on loopback for review, with gateway and outbound-request gates disabled. To restart them later, first verify their recorded PIDs and ports; do not kill unrelated processes or launch duplicate listeners. Use `tools/sandbox/Start-SyntheticLocal.ps1 -PrivateDirectory 'C:\Users\Charles\FacultyConnect-Sandbox\phase2-20261018'`. It refuses occupied ports and never starts ngrok.

## Protected artifacts and preservation

Private directory: **`C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018`**. ACL inheritance is disabled; access is restricted to Charles, SYSTEM and Administrators.

| Artifact | Purpose |
| --- | --- |
| `runtime.env`, `migration.env` | Separate generated credentials/configuration; never publish |
| `synthetic-accounts.private.json` | Synthetic account passwords and TOTP setup, for local use only |
| `identity.json`, `fixtures.json` | Database/account identifiers and scenario IDs |
| `mariadb-validation.json` | Table counts, indexes, foreign keys, scope and concurrency outcomes |
| `https-validation.json` | 33 successful checks without cookie/secret values |
| `provider-creations.jsonl`, barrier/worker files | Mock creation count and independent-process evidence |
| `localhost.key`, `localhost.crt` | Protected TLS private key and public localhost certificate |
| `ngrok.yml` | Validated offline configuration, no token or real public hostname |
| `preservation-before.json`, `preservation-after.json`, `normal-env.sha256` | Source table hashes and normal configuration preservation |
| `storage/`, timestamped PHP/HTTPS logs and process records | Isolated sessions/cache/logs/runtime evidence |

All 23 non-session table hashes remained unchanged in each of `ccis_local_20261018_164245`, `ccis_connect_verified` and `ccis_candidate_20261018`. The normal `.env` hash is unchanged. The original port-3306 server/files and protected recovery archives were not changed. Preservation comparisons exclude volatile sessions; no original data was copied into the synthetic database.

No synthetic Laravel error-level markers or new Windows mysqld crash events were found during this phase. MariaDB integrity/concurrency operations completed successfully. The configured MariaDB error log had no error markers but was not freshly updated during this phase, so it is not complete current console-log evidence. The historical crash remains unexplained. Available C: space was approximately 10.96 GB (decimal).

## Files changed in this phase

- `app/Services/Payments/XenditGateway.php`: stricter captured identity and expiry safety.
- `tests/Feature/XenditGatewayTest.php`: three additional safety tests and improved expiry fixture.
- `tools/sandbox/bootstrap.php`: isolated environment/storage, database identity check and default-deny HTTP gate.
- `tools/sandbox/provision.php`: new-schema/scoped-account provisioning and preservation baseline; refuses existing configuration.
- `tools/sandbox/mariadb-validation.php`: guarded migration/seed, concurrent worker, integrity and privilege checks.
- `tools/sandbox/router.php`, `local-https.cjs`, `Start-SyntheticLocal.ps1`: loopback-only browser/runtime setup.
- `tools/sandbox/https-validation.php`, `preservation-check.php`: synthetic HTTPS and source preservation checks.
- `tools/sandbox/ngrok.example.yml`: offline template with restricted inline policy.
- This report and `docs/xendit-sandbox-trial-checklist.md`.

Setup/prepare helpers are audit tools, not commands to rerun blindly. The synthetic environment contains test history and deliberate blocked attempts. Use the untouched **Synthetic future_trial** contribution for the first approved remote trial, not the already-settled or reserved scenarios.
