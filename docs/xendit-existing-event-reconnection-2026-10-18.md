# Existing event recovery — October 18, 2026

## Scheduled retry readiness update

User reports the existing completed-event retry is scheduled for **October 18, 2026, 10:32 PM GMT+8**. Preparation began at approximately 9:33 PM GMT+8, before that time. No delivery history was assumed to show a completed retry.

The short monitor was replaced with a bounded monitor ending **10:42 PM GMT+8**, allowing ten minutes after the reported retry. A first replacement command was interrupted; state was rechecked and replacement completed successfully. Monitor PID is recorded privately in scheduled-monitor.json. Callbacks were disabled during all 13 repeat denied-route tests, which passed, then existing-payment-only GET verification was re-enabled. No webhook probe, resend or checkout was performed.

Fresh snapshot: trial-20261018-133401-5a6c6d; SHA-256 `5f33a2a4060109fc2ac15c29a9fab761f3e8fd18d754ecb390f6fab5d770982e`. Existing attempt and identifier linkage checks passed, with unchanged recovered/baseline data and normal .env. The contribution remains pending awaiting actual delivery; provider verification and settlement are not yet claimed.

Keep this computer awake with its synthetic backend, MariaDB and network available. The monitor will stop ngrok and disable all activation flags on settlement, callback rejection, error or the stated deadline. Do not click Resend. Earlier ten-minute-from-start scheduling below is superseded by this explicit deadline.

Approved restricted reconnection prepared for attempt 7 only. No new checkout or manual resend was initiated.

Prechecks verified the exact synthetic database on 3307, one pending PHP 10.00 attempt with no submission/receipt, and the stored Session/internal reference/Payment Request linkage. Normal .env and protected recovery row hashes match. Fresh protected 25-table snapshot: `trial-20261018-132453-44c258`, SHA-256 `d35f1166cd5d205036b8b1071821b589dd7dd16bca77ad163bd5bcf013803b0d`. Export completion, coverage and stable row hashes passed; not separately restore-tested.

The existing ngrok configuration targets 127.0.0.1:8002 only. Thirteen denied route/method probes returned 404 with callbacks disabled. Only after those probes passed were callbacks enabled: missing token returned 401 and the read-only return GET returned 200. Private diagnostics: `activation-20261018-212454` under the protected synthetic root.

Recovery-only mode restricts outbound provider requests to GETs for the existing saved Session, Payment Request and Payment IDs. No creation request is permitted through that mode. Callback metadata records only UTC time and HTTP status; provider metadata records resource index, HTTP status and provider status, never identifiers, headers, tokens or payloads.

The user reported an automatic retry scheduled but supplied no exact time. Requested updated retry time; no Resend was clicked. Latest observed status before handoff: pending/unpaid, one attempt, zero submissions/receipts. No fresh provider responses or real callback were observed during that check, so settlement is not yet claimed.

A hidden bounded monitor reads local state every five seconds. It stops the recorded tunnel and disables all three activation flags upon confirmed single TEST settlement, callback rejection, monitor error, or ten-minute timeout. Its terminal evidence is `activation-20261018-212454/recovery-stop.json`, with a preservation check in recovery-preservation.json. Until that evidence or a subsequent check is read, do not claim the runtime is already stopped or settlement succeeded.

Runtime is temporarily enabled only to receive the approved existing event. If no automatic delivery arrives, preserve the attempt and obtain separate approval for manual redelivery. Do not create another payment. After settlement verify exactly one TEST receipt and submission, read the metadata for fresh three-resource provider verification, and confirm preservation. A bounded wait that expires is not proof that the payment failed or retries are exhausted.
