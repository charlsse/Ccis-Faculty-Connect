# Xendit dashboard sample HTTP 400 investigation

No payment was created. Tunnel stopped; all three activation flags are false. No handler or financial record was changed during investigation. Normal application configuration and the three preserved schemas' checked row hashes still match.

## Evidence and limits

The user reports both Payment Session sample deliveries returned HTTP 400 with `Invalid webhook.`. The exact payload and headers were intentionally not retained by our request-inspection configuration. The inspected private PHP/HTTPS logs have no matching request-path/status or validation-reason entries, and the private Laravel log does not exist. The handler catches payload exceptions and returns a generic response without recording the rejecting condition. Consequently an exact historical field-level diagnosis is **not available from local logs**.

The existing ngrok configuration points exclusively to 127.0.0.1:8002. Earlier public-route probes and authenticated/unauthenticated callback probes demonstrated the synthetic router and callback handler. The reported JSON is the controller's payload-rejection response. These facts strongly support correct routing; no request-specific retained trace independently ties each dashboard delivery to the process.

## Validation order and interpretation

In XenditGateway::verifiedWebhookPaymentId:

1. Disabled/unavailable configuration raises GatewayUnavailable, returned as 503.
2. Callback token is compared using hash_equals. A mismatch raises HTTP 401, not this 400.
3. Oversized body or non-JSON content type rejects with 400.
4. JSON parsing, top-level business_id equality, and event string validation reject with 400.
5. Recognized events require a data object and session ID; session validation checks ID format, nested business_id, PAY/PAYMENT_LINK, PHP/PH, amount shape, reference string and known status. These failures become 400.
6. Event/status inconsistency becomes 400.
7. Only then is the local attempt looked up. **No matching attempt returns an empty ID and HTTP 200**, not 400. A found attempt with mismatched reference, amount or session ID rejects with 400.

Therefore the reported 400 from this handler implies the callback token comparison passed for those requests. This verifies equality with the configured token, not independent account ownership. We did not display or obtain the provider token separately.

If the reported sample's top-level business ID differs from configuration, that is the first rejecting condition after JSON validation. If it matches but its data uses IDR rather than PHP, session validation rejects it before any local-attempt lookup (other session fields may also be invalid). Missing local attempt alone cannot explain this response. Without the exact private sample, do not claim which of these conditions occurred first.

## Official contract and acknowledgment

[Xendit's Payment Sessions webhook schema](https://docs.xendit.co/apidocs/webhook-notification-sent-defined-webhook-url-updates-payment-session) documents completed/expired events and merchant/session data. The reviewed documentation does not establish a trusted dashboard-test marker that distinguishes a mismatched sample from a real misrouted event.

[Webhook handling guidance](https://docs.xendit.co/docs/handling-webhooks) recommends authentication and quick 2xx acknowledgment; non-2xx triggers retries. This general guidance is not a documented sample-specific bypass for merchant/currency validation. No blanket 200 exception for mismatched samples was implemented or recommended as an established provider contract.

If needed, propose a separate diagnostic-only change that records bounded reason codes (for example top-level merchant mismatch or unsupported currency), never identifiers, raw payloads, tokens or headers. It would leave responses and settlement checks unchanged. Obtain approval before implementing a sample-acknowledgment behavior change. Alternatively, inspect an exact dashboard sample saved privately outside the web root and report only mismatch booleans; never request it in chat if it includes sensitive fields.

## Saved webhook configuration and next step

[Official setup guidance](https://docs.xendit.co/docs/payments-integration-setup) describes Test & Save but does not establish whether a failed sample delivery still persists the URL. No working automated dashboard access is available. Ask the user to reload Test Mode Webhooks without resending and confirm each saved topic URL persists outside an unsaved edit form. Do not infer persistence from the text entered before the failed test.

The sample failure does not by itself prove that a real PHP sandbox Payment Session would fail: a genuine event should match its merchant, currency and recorded attempt, then require provider-side successful-capture verification. However, do not proceed yet while callback registration persistence is unknown. Confirm saved configuration and resolve any remaining sample diagnostic uncertainty first. No transaction, live channel, manual settlement or creation retry is part of this investigation.
