# Online checkout availability

November 18, 2026: fixed stale Xendit demo checkout reuse and the stopped return tunnel.

Starting or resuming a demo checkout now verifies the configured public return endpoint responds with the expected 303 redirect to the local transaction page. An offline endpoint blocks checkout before reserving a ledger row or creating a provider session. Existing pending sessions are reconciled with Xendit before reuse. Only provider-confirmed expiry without unresolved payment evidence releases the reservation for a fresh session. Unknown creation outcomes remain reserved to prevent duplicate payments. Resume Secure Checkout submits through this same checked flow.

The local demonstration requires the PHP server on port 8000, `node tools/demo-ingress.cjs` on loopback port 8005, and the restricted ngrok endpoint configured in `tools/demo-ngrok.example.yml`. Start the existing tunnel with `tools/Start-MainDemoTunnel.ps1`; it verifies denied routes, the fixed local return, and unauthenticated callback protection. It leaves the full website inaccessible through the public tunnel. Stop/start operations should check for existing processes before launching duplicates. The tunnel is not installed as an automatic startup service; checkout shows a helpful message if it is offline.

Verification: payment feature suites cover stale-session replacement, unknown outcomes, duplicate clicks, offline return blocking, provider verification failure, capture checks, and authorization. `node tools/check-demo-ingress.cjs` verifies the restricted local relay. No live payment was made by this repair. This flow remains Xendit Test Mode; demo confirmation does not settle actual faculty contributions.

Expired provider links cannot be reactivated. Return to the contribution and choose Online Payment to obtain a verified active checkout. Do not reopen a previously expired browser tab.

Follow-up: the affected session was EXPIRED while its BPI_DIRECT_DEBIT request remained REQUIRES_ACTION without any payment or capture. Xendit rejects cancellation for that channel. Demo-only reconciliation now releases that expired hosted checkout when the verified request has no captures, latest payment ID, or latest payment object. The normal settlement gateway retains its stricter reservation rules. Unresolved expired checkouts are flagged and cannot redirect back to the old URL. The affected demo reservation was reconciled and a replacement session was verified ACTIVE through the provider API. No payment was submitted. The updated payment suites pass 43 tests.
