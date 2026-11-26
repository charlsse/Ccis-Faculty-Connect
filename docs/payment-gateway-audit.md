# Optional payment gateway: current-state audit and integration boundary

Historical pre-integration audit. The PayMongo sandbox adapter has since been implemented; see [the current implementation and setup guide](paymongo-sandbox-integration.md). The architecture below describes the earlier baseline, including its former human-verifier requirement.

## Current architecture confirmed

- `Payment` / `payments`: separate general manual contribution ledger, not a gateway transaction store.
- `WaterCollection`: collection terms, one versioned QR destination, member assignments; published terms immutable.
- `WaterMember`: assigned user and exact amount; statuses unpaid, pending, paid, rejected, waived. This remains the business obligation.
- `WaterSubmission`: manual QR or cash attempts; required paid_at; optional proof; reference claims and sequential per-member attempts. Pending means manual verification, not provider processing.
- `WaterReceipt`: one receipt per obligation and per approved submission, requires a human verified_by FK; encrypted snapshot/reference.
- `WaterPaymentService`: server-side cents comparison, transactional locks, reference ownership, review, cash, waiver, existing receipt and notification creation.
- `WaterNotice` feeds existing portal notifications. AuditLogger records water events without private references/notes.
- Reports, CSV, HTML/PDF receipts, verification views and history currently use human-verifier and QR/cash wording.
- Redesigned checkout, category navigation, mobile selector, proof preview, retry prompt, collection sections and officer personal checkout are present.
- Middleware preserves authentication, active-account/onboarding/2FA checks; payment management gates and private proof retrieval remain in place.
- No gateway adapter/configured provider was found in application configuration, services, routes, or dependencies. No live credentials were inspected or displayed.

## Least disruptive proposed integration

1. Keep WaterMember as the obligation and existing receipts/reports as the accounting output.
2. Add a narrowly scoped gateway-attempt table linked to WaterMember, not a second ledger. The current Payment table is unrelated and WaterSubmission requires a completed-payment timestamp, so neither safely models uncertain provider attempts without conflating financial states.
3. Persist a random unique local reference, expected amount in minor units, PHP currency, sandbox environment, provider/payment identifier, attempt state, and confirmation/failure metadata. Never persist provider secrets or full webhook bodies.
4. Serialize initiation against the member row; reuse an unresolved attempt rather than create another. Preserve the unresolved state after timeouts. Avoid holding database locks during network calls. Provider-specific idempotency and lookup-by-reference behavior must be verified first.
5. Manual submission, cash recording and waiver must consult the same unresolved attempt reservation under consistent locks. An unavailable gateway must not block unrelated manual-only obligations; an already-started unknown charge must not silently permit a second payment.
6. After authenticated provider verification and exact identity/reference/amount/currency/environment matching, lock the attempt and member, create one approved gateway-channel submission, one existing receipt, and one notification in one database transaction. Preserve unique obligation/receipt constraints.
7. Extend receipt verifier representation to allow explicit provider verification without inventing a human officer. Update history, HTML/PDF receipts and reports to display Online / Automatically Verified versus Manual QR / Officer Verified. Do not relabel historical manual records.
8. Duplicate, concurrent, delayed and out-of-order events must not repeat receipts/notifications or regress settled states. If a late successful charge conflicts with an already-settled obligation, retain a reconciliation exception rather than discard evidence or create a second receipt. Refund handling needs documented provider support and separate authorization.
9. Browser return GETs display state only. A protected backend reconciliation action can query the provider; limited polling reads local status. Unknown is neither failed nor safely retryable.
10. Retain current manual flow. Show online checkout as recommended only when a real configured sandbox adapter is available, with clear test-mode labeling. Sandbox payments must use a dedicated test database/staging environment, never mark real dues paid.

## Implemented in this preparation

- `app/Contracts/PaymentGatewayInterface.php`: provider contract for checkout creation, authenticated retrieval, and provider-authenticated webhook identification. It has no fake implementation or payment mutation capability.
- `config/payment_gateway.php`: reserved configuration, disabled and sandbox by default, no selected provider.
- `.env.example`: only PAYMENT_GATEWAY_ENABLED=false, PAYMENT_GATEWAY_ENV=sandbox and an empty PAYMENT_GATEWAY_PROVIDER placeholder. Existing `.env` was not changed.

There is deliberately no service-container binding, public webhook, CSRF exemption, provider URL, fabricated authentication scheme, credential field, or production switch. Setting the reserved enabled flag does not activate payments.

## Required next input and verification

The request mentions Maya as an example but does not select a provider. Confirm the intended provider first. Then verify that provider's current official documentation for its specific checkout product, sandbox endpoints, request/response schema, amount units, idempotency behavior, authenticated transaction retrieval, webhook verification/retries, terminal states, test methods and permitted callback URLs.

Sandbox secrets must be set locally in environment configuration after the exact required fields are established; do not paste them into chat, screenshots, or source. A secure HTTPS staging URL or approved HTTPS development tunnel will be needed for real webhook delivery; localhost alone cannot receive external callbacks. No tunnel was opened or local app exposed.

Provider-specific credential names, dashboard setup commands, webhook registration steps and test scenarios cannot be truthfully specified until the provider/product is selected. No official API protocol has been assumed. No real-money or sandbox provider request has been made.

The requested gateway success/webhook/idempotency/concurrency tests are pending the adapter and persistence implementation. Regression tests below cover preservation of the existing application, not provider confirmation. No new UI was introduced at this abstraction-only stage, so prior screenshot results are not claimed as new gateway visual QA.

Current preparation verification: PHP syntax checks passed. Full application suite passed: **108 tests, 931 assertions**. Results: storage/logs/gateway-preparation-tests.txt and storage/logs/gateway-preparation-tests.xml. No provider integration or sandbox transaction was verified.
