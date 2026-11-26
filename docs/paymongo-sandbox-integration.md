# PayMongo sandbox water payments

**EXPERIMENTAL / OPTIONAL / FUTURE INTEGRATION.** The primary production workflow is [Treasurer QR and manual verification](treasurer-payment-workflow.md). PayMongo remains disabled and is not a prerequisite for normal FacultyConnect payments. Disabled options are hidden from faculty. Earlier UI descriptions below describe the sandbox experiment, not the primary payment architecture.

Implemented September 28, 2026. Disabled by default. No real PayMongo transaction has been performed during implementation. The existing application has not been configured or migrated for PayMongo. The separate collection-category migration supports normal Treasurer payments only.

## Behavior

Assigned faculty, including a Treasurer paying their own assigned contribution, can choose hosted online test checkout. The adapter requests QR Ph, the interoperable channel for compatible GCash and MariBank apps in a live merchant arrangement; it does not pretend to provide a separate MariBank API. Merchant eligibility and QR Ph activation still require PayMongo confirmation. This implementation rejects production environments and live keys.

**Use the provider simulator only. Never scan a test QR with a real banking or wallet app.** PayMongo explicitly warns that QR Ph test codes can trigger real transactions. The checkout displays this warning and requires acknowledgement. Live payment support is outside this implementation.

Manual QR/proof, cash recording, review, waiver, reporting and receipts remain available. An unresolved online attempt reserves its contribution so manual or duplicate online payment cannot settle it simultaneously. Offline configuration leaves manual payment usable, including before the new migration is applied.

Online confirmation creates the existing approved submission, paid member state, receipt, audit and notice in one transaction. The method is `paymongo_test`; receipts explicitly say TEST and are not valid for real dues. No human reviewer is impersonated. Never enable this against actual outstanding contributions: test confirmation updates the same ledger used by the sandbox application.

## Configure an isolated sandbox

1. Use a separate local/staging application and database containing synthetic contributions. Preserve the application's existing APP_KEY for any existing encrypted records. Do not run destructive database reset commands against the working database.
2. Obtain a PayMongo **test secret key** and enable hosted QR Ph testing for the account. Store secrets locally in the sandbox `.env`; never paste them into chat, screenshots, source control or browser code.
3. Configure the sandbox database, an externally reachable HTTPS APP_URL, and these settings:

```dotenv
APP_ENV=staging
PAYMENT_GATEWAY_ENABLED=true
PAYMENT_GATEWAY_ENV=sandbox
PAYMENT_GATEWAY_PROVIDER=paymongo
PAYMENT_GATEWAY_SANDBOX_DATA_CONFIRMED=true
PAYMONGO_SECRET_KEY=sk_test_REPLACE_LOCALLY
PAYMONGO_WEBHOOK_SECRET=REPLACE_WITH_TEST_WEBHOOK_SIGNING_SECRET
WATER_EMAIL_ENABLED=false
```

Set the data-confirmation flag only after checking database isolation. Production APP_ENV, live keys, missing secrets or missing gateway tables keep the option unavailable. No public key is needed for hosted checkout. Keep APP_DEBUG=false on the reachable sandbox and retain normal authentication/2FA.

4. Only for that explicitly requested sandbox, run normal application migrations, then `php artisan migrate --path=database/experimental/paymongo` and `php artisan config:clear`. The optional migration creates `water_gateway_attempts` and permits a null human verifier on automatically generated receipts. Its rollback deliberately refuses to discard existing gateway history. It is outside the normal migration folder and is not needed for Treasurer QR payments.
5. Register the HTTPS endpoint `/webhooks/payments/paymongo` in PayMongo **test mode**, subscribed to `checkout_session.payment.paid`. Configure its signing secret above. No tunnel or provider webhook registration was created by this implementation. This endpoint alone is exempt from CSRF; it requires a valid test signature and timestamp.
6. Create a synthetic published collection and assign a synthetic faculty member (or Treasurer). Start checkout once, acknowledge simulator-only testing, and complete the provider's test simulator flow. Confirm simulator availability with PayMongo if the hosted screen does not expose it; do not substitute a bank-app scan.
7. Verify webhook delivery, one paid submission/receipt, TEST labeling, report entry, notification, repeat-delivery idempotency and browser return. Test delayed delivery and use Check Again to reconcile. Keep the integration disabled until this genuine sandbox round trip succeeds.

The normal application `.env` is unchanged. Placeholder credentials in QA helpers are synthetic and are blocked from outbound requests.

## Trust and retry rules

- The server derives identity, reference, amount in centavos and PHP currency from the authorized assignment. Browser input cannot set the price. Checkout creation uses a persisted UUID and Idempotency-Key, fixed PayMongo API host, finite timeouts and no automatic HTTP retries.
- Only HTTPS `checkout.paymongo.com` checkout URLs are accepted. Stored URLs are encrypted. API credentials, raw provider payloads and checkout URLs are not exposed in status JSON or exception chains.
- Webhooks validate HMAC SHA256 over timestamp + period + exact request body, use the test signature, reject live events and enforce a five-minute timestamp tolerance. Keep the host clock synchronized.
- A valid webhook triggers authenticated resource retrieval; payload assertions and success/cancel redirects never settle money. Session ID, local reference, amount, currency, test mode, payment ID, QR Ph source and paid timestamp must agree.
- Database locks and unique constraints reserve one active attempt and enforce unique provider session/payment IDs and receipts. Replayed confirmation returns the existing result. Manual review cannot reapprove an online receipt.
- Failed individual payment attempts do not mean the hosted checkout is safely cancelled. Unknown creation, malformed responses and network failures remain reserved. Expiry only releases a verified expired session without unresolved payments or a nonterminal intent. Late/conflicting success enters investigation rather than duplicating settlement.
- Browser GET return/status requests are read-only. Polling reads local state at five-second intervals, at most twelve requests. Check Again is an authenticated CSRF-protected POST with a provider-check cooldown. Webhooks can confirm after the browser closes.

## Reconciliation and exceptions

`php artisan water:gateway-reconcile` checks up to 100 reserved attempts with known session IDs. It creates no charges. It is not automatically scheduled; invoke it on the isolated deployment as needed, or configure its scheduler there.

If checkout creation timed out before a session ID was saved, locate the actual session in the PayMongo test dashboard by the full `FC-WATER-<UUID>` reference. Run `php artisan water:gateway-reconcile --session=cs_ACTUAL_ID`. The command retrieves the session and independently matches its trusted reference and payment details. Never invent an ID or release the reservation based solely on a screenshot, browser cancellation or elapsed time. PayMongo's creation idempotency retention is limited; blindly retrying later may create a second checkout.

Unknown and conflicting outcomes appear in the officer's online exception list. There is no unsafe force-paid or force-release control. Investigate the provider session before choosing any operational recovery. Refunds, live payments and settlement accounting are not implemented.

## Verification and limits

The full automated PHP suite passes: **132 tests, 1,129 assertions**, including 24 gateway tests (198 assertions). Coverage includes authorization, price tampering, missing configuration, unsafe URLs, network failures, signed/forged/stale/live events, amount/currency/reference mismatches, duplicate and interleaved confirmations, manual-payment conflicts, recovery, cooldown, receipts, notices and reports. HTTP calls in these tests are mocked and stray requests prohibited.

The isolated browser harness checks 10 screens at 13 widths (320–1920px), in light/dark themes: 260 layout checks, input sizing, manual fallback, reserved-payment forms, status states, exceptions, reports, PDF download and bounded local polling. Screenshots and results are under `storage/app/private/paymongo-qa/`; browser fixtures never contact PayMongo. Sample mobile screenshots were visually inspected.

To repeat browser QA: use the guarded `tools/paymongo-qa-fixture.php` once, then `tools/paymongo-qa-states.php`; serve `php -S 127.0.0.1:8096 -t public tools/paymongo-qa-router.php`; run `node tests/Browser/paymongo-status.cjs`. On subsequent runs, refresh consumed synthetic recovery codes with `php tools/paymongo-qa-refresh-login.php`. These helpers require the existing local Playwright installation and Chrome. They are not application routes.

Not verified: an actual PayMongo sandbox checkout/webhook, merchant QR Ph eligibility, a public HTTPS deployment, true multiworker MySQL contention, physical-device wallet flows or live settlement. Interleaved automated tests and database constraints are useful coverage, not proof of production concurrency behavior. Do not treat mocked fixtures as successful provider integration testing.

## Main implementation files

- `app/Services/Payments/{PayMongoGateway,WaterGatewayService,GatewayUnavailable}.php`, `app/Contracts/PaymentGatewayInterface.php`
- `app/Models/WaterGatewayAttempt.php`, `app/Http/Controllers/WaterGatewayController.php`
- `database/experimental/paymongo/2026_09_28_120000_add_water_gateway_attempts.php`
- `config/payment_gateway.php`, `.env.example`, provider binding, `bootstrap/app.php`, `routes/water.php`, `routes/console.php`
- Checkout/status/review/queue/receipt views, member dashboard/collection links, `public/js/water-online.js`, existing payment-service guards
- `tests/Feature/PayMongoGatewayTest.php`, `tests/Browser/paymongo-status.cjs`, isolated QA helpers

No new production dependency was installed. A scoped pre-change backup is retained privately in `storage/app/private/paymongo-baseline`.

## Official references

- [Hosted checkout creation](https://docs.paymongo.com/docs/payment-channels-hosted-checkout-quick-start) — v2 creation; [retrieval](https://docs.paymongo.com/reference/get_checkout_sessions) uses v1.
- [Checkout resource](https://docs.paymongo.com/reference/checkout-session-resource) and [hosted checkout events](https://docs.paymongo.com/docs/payment-channels-hosted-checkout).
- [Webhook authentication](https://docs.paymongo.com/docs/developer-tools-webhook-setup-management) and [idempotency](https://docs.paymongo.com/reference/idempotent-requests).
- [QR Ph](https://docs.paymongo.com/docs/payment-acceptance-qr-ph), [MariBank transfers/QR Ph](https://www.maribank.ph/product/transfers), and [critical QR Ph testing instructions](https://docs.paymongo.com/docs/payment-acceptance-testing).
