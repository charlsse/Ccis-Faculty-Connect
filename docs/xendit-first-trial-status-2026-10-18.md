# First Xendit trial — completed; historical status log

**Final outcome:** attempt 7 settled via the authenticated webhook on October 18, 2026 at approximately 10:32:51 PM GMT+8. Exactly one PHP 10.00 synthetic submission and one TEST receipt were verified. ngrok is stopped and all three activation flags are false. See [final redacted report](xendit-sandbox-completed-2026-10-18.md). All earlier pending/active descriptions below are historical.

## Latest update — capture contract corrected offline

See `xendit-capture-contract-correction-2026-10-18.md`. The corrected Payment-based verification passes the preserved provider responses with mocked HTTP only. All four regression suites pass (73 tests, 812 assertions). Attempt 7 remains pending, no receipt/submission was created, all flags are false, and ngrok is stopped. Remote reconnection and webhook processing still await separate confirmation. Older contract-mismatch and activation descriptions below are historical.

## Latest outcome — simulator success reported, settlement unconfirmed; trial stopped

The user reported successful virtual simulation. Subsequent read-only checks still show exactly one local attempt (7), status pending, member unpaid, zero submissions and zero receipts, no confirmed provider-payment identity and no confirmation timestamp. The browser/simulator outcome alone was not accepted as financial confirmation.

The recorded ngrok process was stopped and PAYMENT_GATEWAY_ENABLED, TUNNEL_ENABLED and FC_SANDBOX_REMOTE_APPROVED were reset to false. Final process check found no ngrok/cloudflared process. Normal .env and all checked row hashes for the recovered application and two preserved schemas remain unchanged.

No second session was created, no payment creation was retried, and no contribution was manually marked paid. The pending attempt and active reservation remain intact. Existing protected response, snapshot and activation diagnostics are retained. A real completed-webhook delivery status has been requested from the user without retrying delivery or exposing headers/payloads.

End-to-end settlement has **not passed**. The available evidence does not establish whether the real callback was absent, rejected, delayed, or whether provider-side verification failed. No particular API contract mismatch can yet be claimed. The earlier HTTP 201 established accepted session creation, not successful capture. Do not initiate another transaction to diagnose this; investigate the existing session and its real callback first.

All active-tunnel descriptions below are historical and superseded by this stopped state.

## Current state — one session created, awaiting user simulator action

User confirmed both Test Mode webhook URLs persisted after page refresh. This is user-assisted registration verification. The sample-payload HTTP 400 behavior was not weakened.

Fresh snapshot: `trial-20261018-121343-1dabf6` under the protected synthetic root; SHA-256 `4664f19634e7b770c62da4d355b669ead6dae111b027d64189c53f2af66a00a7`. Source preservation, unused contribution, restricted routes and callback authentication passed again. Activation diagnostics are in `activation-20261018-201343`.

Exactly one POST creation was sent through the existing faculty-authorized gateway service. Xendit returned HTTP 201. Local attempt 7 is pending for PHP 10.00 (1000 minor units); the accepted hosted checkout was opened in the user's browser without printing its URL. A one-shot creation lock prevents blindly rerunning the helper. Provider response and checkout URL remain protected in the synthetic root, not in this report.

Latest read-only status: one attempt, unpaid member, zero submissions, zero receipts, no confirmed provider-payment identity or confirmation timestamp. No simulator action or successful capture has yet been observed. Browser automation remains unavailable; user assistance was requested for the explicit virtual Test Mode simulator only.

The restricted port-8002 tunnel and all three trial flags are temporarily active to receive callbacks and perform required server-side verification. Port 8000 is not exposed. Stop the recorded tunnel and disable all flags on completion, error or abandonment. Never create another session, manually mark paid, or infer settlement from the return page. Earlier stopped-status sections below are historical.

## Latest status — stopped after dashboard webhook test failure

The user reported that one or both Payment Sessions dashboard tests failed. The specific topic, HTTP status and generic error wording have been requested; the cause is not established. No checkout session or payment creation was attempted.

The recorded synthetic ngrok process was stopped. A subsequent check found zero ngrok/cloudflared processes and confirmed PAYMENT_GATEWAY_ENABLED=false, TUNNEL_ENABLED=false and FC_SANDBOX_REMOTE_APPROVED=false. Normal .env and all checked preservation hashes still match. The protected activation directory contains stopped.json recording this outcome. Do not run further dashboard delivery tests while the tunnel is stopped. Investigate the reported status before resuming; do not weaken callback authentication or provider verification to accept a dashboard fixture.

The active-tunnel section below is historical, not the current runtime state.

## Resumption — restricted tunnel active, awaiting manual webhook registration

The user verified the assigned domain as “Your dev domain” in their signed-in ngrok account and confirmed Full Capture disabled and no log-export destinations. These are **user-assisted dashboard checks**, not independent automated verification. This supersedes the dashboard-access prerequisite below; the earlier section remains historical evidence.

A fresh 25-table snapshot passed on resumption at `C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018/trial-20261018-120421-234321`, SHA-256 `3f20edf22e593ac1471914b004c5111abaf4441c8a9841a858eccd32fbb19dc7`. Database identity, unused PHP 10.00 contribution and preservation hashes passed again.

The approved ngrok process successfully started the configured domain with upstream **127.0.0.1:8002**. Port 8000 is not configured. Thirteen denied-route/method checks returned 404. Missing and invalid callback authentication returned 401; a valid-token unknown-event probe returned 200 with no changes to members, gateway attempts, submissions or receipts. This probe did not call Xendit or settle a contribution.

Private diagnostics: `C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018/activation-20261018-200505`. Raw agent output is private; do not publish it.

Current temporary flags: PAYMENT_GATEWAY_ENABLED=true and TUNNEL_ENABLED=true (required by the current middleware for callback access), FC_SANDBOX_REMOTE_APPROVED=false. Outbound Xendit API requests remain blocked. No checkout session has been created. The restricted tunnel is awaiting user-assisted Test Mode registration of **Payment Session – Completed** and **Payment Session – Expired**, both to `https://skid-porridge-snoring.ngrok-free.dev/webhooks/payments/xendit`. Do not register other event topics. User should report only Test & Save outcome, no tokens or payloads.

Payment creation must wait for successful registration. On completion or abandonment, stop the recorded ngrok process and reset all three flags to false. Prior transaction approval remains for one session only; never retry ambiguous creation.

---

Historical pre-activation report follows:

The approved single PHP 10.00 virtual transaction has **not been attempted**. No hosted checkout session was created, no webhook was registered, and no provider API request or tunnel launch occurred. All three activation flags remain false.

## Completed prerequisite work

- Exact synthetic database identity verified: ccis_xendit_test_20261018_111928 on 127.0.0.1:3307, recovery mode zero.
- Credential presence, Test Mode key prefix, operator Treasurer confirmation and cookie protections passed private preflight. No secret values were printed.
- The future_trial member is unpaid, assigned PHP 10.00, and has no gateway attempt.
- Normal .env and all 23 non-session table hashes in the normal recovered database and two protected recovery schemas match preservation baselines.
- New protected snapshot directory: `C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018/trial-20261018-115654-719021`.
- synthetic-before.sql exited zero, contains a completion marker and all 25 expected table definitions. Before/after source row counts and content hashes match. SHA-256: `5f29c112c64a8ffa663e79f813bb4538412ed0ea2757a3c907a68b3abda8da60`.
- table-baseline.json and snapshot-result.json preserve verification metadata. This is a scoped table/row logical snapshot, excludes triggers/events/routines, and has not been restore-tested. It is not described as a full server backup.

## Blocking prerequisite

The user explicitly required independent ngrok account/domain ownership verification before activation. Access to signed-in dashboards failed: both browser control and the Computer Use Node runtime reported `failed to write kernel assets: The system cannot find the path specified. (os error 3)` during initialization.

Local hostname configuration and possession of an authtoken do not independently prove that the assigned domain belongs to the intended account. The agent did not bypass this prerequisite by starting a tunnel as an ownership test. Dashboard registration of the Payment Sessions callbacks and virtual simulator interaction also require working account access or user-assisted operation.

Restore browser/Computer Use access and make the signed-in ngrok Domains page and Xendit Test Mode dashboard available, without exposing token/key pages. Alternatively, arrange user-assisted dashboard verification and registration; do not request secrets in chat. Existing approval for the specified single transaction remains in place, but no activation should occur until the stated prerequisite is satisfied.

No API contract mismatch or payment failure was observed because no remote payment request was made. The contribution remains unused. Repeat freshness and unused-contribution checks on resumption. Preserve this snapshot and never retry session creation blindly if a later request becomes ambiguous.
