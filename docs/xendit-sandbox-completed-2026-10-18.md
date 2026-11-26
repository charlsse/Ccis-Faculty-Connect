# Xendit PHP 10.00 sandbox trial — completed

The existing synthetic transaction settled through the authenticated webhook and corrected server-side verification. Read-only final checks passed. No second Payment Session, manual financial update, live payment or payout was performed.

## Delivery and verification evidence

All times below are October 18, 2026, GMT+8 (Asia/Manila).

| Evidence | Outcome |
| --- | --- |
| Fresh Session GET, 22:32:48 | HTTP 200; COMPLETED |
| Fresh Payment Request GET, 22:32:49 | HTTP 200; SUCCEEDED |
| Fresh Payment GET, 22:32:50 | HTTP 200; SUCCEEDED |
| Local webhook completion, 22:32:51 | HTTP 200 |
| Monitor stop, 22:32:51 | settlement_verified; activation flags disabled |
| User-reported dashboard timestamp, 22:32:54 | Completed after three failed delivery attempts |

The few seconds between local and dashboard timestamps are recorded as separate observations, not an inferred discrepancy. Callback authentication is evidenced by the handler's mandatory token check before provider verification and settlement; incorrect tokens return 401. No token value was logged. Recovery-only outbound restrictions permitted GETs for the existing three provider identities only. No manual reconciliation command ran.

The corrected verifier required linked Session/Request/Payment identities, matching merchant, Session/internal reference, PHP/PH, PAY, automatic capture, successful Payment state and valid unique captures totaling exactly 1000 minor units. Successful execution of this path and the matching local records establish the verified amount. Metadata intentionally omits raw provider bodies and secret identifiers.

## Local settlement checks

- Database: ccis_xendit_test_20261018_111928 on port 3307.
- Attempt 7: confirmed, no issue code, confirmation timestamp and provider payment identity present.
- Contribution: paid; active reservation released.
- Exactly one attempt for this member; provider session and payment identities are each unique in the attempt table.
- Exactly one approved contribution submission and one linked receipt.
- Attempt, member, submission and receipt all match PHP 10.00.
- Submission and receipt method: xendit_test. Receipt snapshot identifies Automatic - Xendit TEST; the receipt template displays TEST and the sandbox/not-valid-for-real-dues disclaimer.
- Internal references and member/submission/receipt links match.

No duplicate settlement exists in the final state. No extra provider resend was performed to manufacture a duplicate event; duplicate/late-event behavior was covered by the earlier passing regression suite (73 tests, 812 assertions).

## Preservation and shutdown

Normal .env is unchanged. All 23 checked non-session tables in each of the normal recovered database and both protected recovery schemas match their preservation baselines. Existing real faculty/payment/collection records were not changed. Session tables are intentionally excluded from those logical preservation baselines because normal local sessions are volatile.

No ngrok/cloudflared process remained at final verification. PAYMENT_GATEWAY_ENABLED=false, TUNNEL_ENABLED=false, FC_SANDBOX_REMOTE_APPROVED=false. Normal port 8000 was not tunneled; only the synthetic 8002 upstream was used. No unnecessary service restart occurred during final verification.

Final verification used a read-only database transaction and local metadata only: no provider requests, financial writes, webhook resend or new payment. Sixteen final linkage/uniqueness/amount/safety checks passed.

## Protected evidence

Root: C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018

- recovery-webhook-metadata.jsonl: callback HTTP result/time, no headers/body.
- recovery-provider-metadata.jsonl: resource indices, HTTP status and provider state, no credentials or IDs.
- activation-20261018-212454/recovery-stop.json: automatic shutdown outcome.
- activation-20261018-212454/final-settlement-verification.json: final redacted checks.
- trial-20261018-133401-5a6c6d: last pre-retry table/row snapshot, SHA-256 5f33a2a4060109fc2ac15c29a9fab761f3e8fd18d754ecb390f6fab5d770982e. Export verified, not separately restore-tested; excludes triggers/events/routines.

Private diagnostic responses and earlier snapshots remain preserved. Do not publish that directory.

## Limits and remaining work

This completes one virtual PHP 10.00 Test Mode transaction and its offline-delivery recovery, not production payment certification. The earlier sample-payload rejection policy remains unchanged. Merchant ownership, saved callback configuration and dashboard delivery details were user-assisted observations. The previously exposed ngrok token remains unrotated by user choice. Public activation or additional transactions require a new scope/decision; live-mode operation was not tested or enabled.
