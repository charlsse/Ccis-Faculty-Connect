# Xendit capture contract correction — October 18, 2026

Implemented and tested offline. No tunnel, webhook resend, new Payment Session, remote provider lookup or settlement occurred in this phase. Attempt 7 remains pending/unpaid with zero submissions and receipts; all three activation flags remain false.

## Contract correction

The adapter now performs Session → Payment Request → Payment GET verification using linked provider identities. It requires the Request to be SUCCEEDED but never treats that alone as capture evidence. It fetches `/v3/payments/{payment_id}` with api-version 2024-11-11 and requires the Payment to be SUCCEEDED, with matching Payment/Request IDs, merchant, PHP/PH, PAY, AUTOMATIC and exact request amount. The Session/internal reference and local amount checks remain unchanged.

Capture evidence comes from that authenticated Payment response. Each capture requires a unique nonempty string identifier, numeric positive amount representable in minor units, and a valid timezone-bearing ISO timestamp. Identifiers are opaque: the schema documents a string, not a required example prefix/UUID pattern. Local defensive limits reject whitespace/control characters, invalid UTF-8 and identifiers over 255 bytes. Timestamps reject invalid calendar dates, missing timezone, malformed values and parser warnings. The capture sum must exactly equal the verified session amount (1000 minor units for the existing trial).

No per-capture status is invented: documented captures contain capture_id, capture_timestamp and capture_amount. If an unexpected status is supplied and contradicts success, the adapter fails closed. Duplicate IDs, empty/malformed capture lists, inconsistent amounts and conflicting capture evidence exposed by both Request and Payment also fail closed. Request/Payment reference fields are not substituted for the Session's internal reference; the linkage uses verified provider IDs and the existing Session/local reference checks.

Source: [Xendit Get Payment schema](https://docs.xendit.co/apidocs/get-payment), reviewed October 18, 2026. The example capture ID is not a normative prefix restriction. The 255-byte identifier limit is an application defensive bound, not a claimed provider schema limit.

## Changed files

- app/Services/Payments/XenditGateway.php — Payment lookup and strict capture validation.
- tools/sandbox/bootstrap.php — adds the Payment GET URL family to the sandbox's existing remotely-approved allowlist; the persistent approval flag remains false.
- tests/Feature/XenditGatewayTest.php — separate Request and Payment mocks, capture validation cases, unchanged financial idempotency/authorization assertions, contradictory-evidence test.
- tests/Fixtures/xendit-payment-captured.json — sanitized PHP 10.00 structural fixture with fabricated identifiers, no credentials/customer data.
- tools/sandbox/verify-recorded-capture.php — offline replay of protected historical responses through a mock-only HTTP client; no reconciliation call or database mutation.
- This report and the current trial status note.

No migration or environment-file edit was needed. The diagnostic helpers from the prior phase remain historical; their failed capture-summary is not overwritten or relabelled as passed.

## Verification

Xendit, PayMongo, WaterCollection and PaymentModule suites: **73 tests, 812 assertions, all passing**, using the test environment's SQLite memory database. Cases include valid opaque IDs without per-capture status, missing/empty captures, invalid timestamps, duplicate/malformed IDs, incorrect amounts/types, mismatched Payment/Request/merchant identities, wrong currency/country/type/capture mode, pending/failed/authorized Payment states, contradictory evidence and exact multi-capture sums. Existing webhook authentication, duplicate/late notification, manual collection and role-permission tests passed.

The three existing protected provider responses were replayed through the corrected adapter with all HTTP calls mocked and stray requests blocked. They now verify as succeeded, exact PHP 10.00, matching internal reference. Attempt 7's raw attributes were unchanged. Evidence: `diagnosis-20261018-124443/corrected-offline-verification.json` under the protected synthetic root. This validates the recorded responses, not a fresh live provider state.

Final checks found no ngrok/cloudflared process, all activation flags false, the same synthetic identity, and all checked original recovered/baseline row hashes and normal .env unchanged. The source adapter changed as authorized, but no production-intended database records or configuration changed.

## Remaining uncertainties and safe webhook recovery

The real callback has not yet been reprocessed. The user reports an automatic retry scheduled but has not supplied the exact time; confirm current retry status before activation. Provider state must be fetched fresh during actual callback handling. No live end-to-end settlement success is claimed by this offline pass.

After separate reconnection confirmation: preserve current synthetic state, verify target identity and restrictive ngrok configuration, launch only upstream 8002 while callback handling is still disabled, and test all denied public routes. Then enable the approved callback/GET verification path. The older launch helper enables callbacks before probing, so revise that sequence before use. Automatic delivery may arrive immediately once enabled.

Prefer the existing automatic redelivery; obtain separate confirmation before any manual resend. Never create another Session. Accept settlement only through the authenticated matching webhook and fresh provider verification. Confirm one submission and one TEST receipt, no duplicate financial changes and preservation of normal data; stop ngrok and disable every activation flag afterward. If any mismatch remains, preserve pending state and diagnostics.
