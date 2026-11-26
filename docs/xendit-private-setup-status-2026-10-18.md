# Xendit private setup status — October 18, 2026

The user postponed ngrok authtoken rotation. Leave that token unchanged and continue local preparation only. This does not authorize remote activation. The exposed token remains a security concern for a later activation decision, even with synthetic data. Synthetic database password, APP_KEY, TLS bridge token and account credentials were already rotated successfully; see synthetic-credential-rotation-2026-10-18.md.

## Current checks

Private preflight confirms the exact synthetic database on port 3307, three synthetic users, no trusted devices, sandbox mode, Secure/HttpOnly cookies and SameSite=lax. PAYMENT_GATEWAY_ENABLED, TUNNEL_ENABLED and FC_SANDBOX_REMOTE_APPROVED are false. ngrok offline configuration check passes. The normal .env and 23 non-session tables in each of the three preserved recovered schemas match their saved preservation hashes. No normal database or application configuration was changed.

Xendit key, callback token and merchant business ID are still absent. Treasurer confirmation is false. No claim of merchant verification or valid provider credentials can be made yet. The prior post-rotation 33 HTTPS assertions passed; they were not rerun during this configuration-only check.

## Private entry steps

Use only `C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018/runtime.env`. Do not paste its contents into chat or include it in IDE context.

1. Sign into the intended Treasurer-controlled merchant account in Xendit's dashboard and explicitly select Test Mode. Confirm the account/business identity with the Treasurer; a local application Treasurer role does not establish control of a Xendit merchant account.
2. Open Settings > Developers > API Keys. Create a Test Mode secret key with the minimum money-in permissions needed to create Payment Sessions and read Sessions/Payment Requests; no payout privileges. Store it as XENDIT_SECRET_KEY. Do not use a public key or a live-mode key. [Official setup](https://docs.xendit.co/docs/payments-integration-setup).
3. Obtain the webhook verification token for this same merchant and store it as XENDIT_CALLBACK_TOKEN. This is distinct from the API secret and ngrok token. Incoming notifications are checked against x-callback-token; do not put the token in the callback URL. [Official webhook verification](https://help.xendit.co/hc/en-us/articles/360038072991-How-to-validate-if-the-webhook-is-sent-from-Xendit).
4. Obtain the exact merchant business ID from verified account details or Xendit support if it is not visible. Save it as XENDIT_BUSINESS_ID; do not substitute a display name, user ID, public key or guessed identifier.
5. After the Treasurer confirms this is the intended merchant account, set XENDIT_TREASURER_ACCOUNT_CONFIRMED=true. This records operator confirmation, not independent remote verification. During an approved trial, returned provider business IDs must also match before settlement.
6. Preserve all three false activation flags, database settings, APP_KEY and HTTPS protections. Close runtime.env and report only that private entry is complete. Presence/format checks can then run without printing values or making remote API calls.

## Webhook configuration prepared, not registered

In Test Mode, the intended dashboard topics are **Payment Session – Completed** and **Payment Session – Expired**. Their events are `payment_session.completed` and `payment_session.expired`. These are Payment Sessions topics, not the older Payment Link topic. [Official event schema](https://docs.xendit.co/apidocs/webhook-notification-sent-defined-webhook-url-updates-payment-session).

Both topics use this POST endpoint:
`https://skid-porridge-snoring.ngrok-free.dev/webhooks/payments/xendit`

Read-only browser return endpoint:
`https://skid-porridge-snoring.ngrok-free.dev/payments/xendit/return`

Do not register these URLs or click Test & Save yet: that initiates callback delivery. The public tunnel remains off. The policy preserves upstream 127.0.0.1:8002 and denies all other public method/path combinations. Local synthetic browser access remains https://localhost:8443; normal port 8000 is never tunneled. A return redirect cannot mark a contribution paid; successful capture must be verified server-side against matching merchant, references, identities, amount and currency.

## Remaining requirements for one simulated PHP 10.00 transaction

- Private Xendit credential entry and format checks; Treasurer confirmation for the exact merchant ID.
- Confirm current-user trust for the verified localhost certificate, without bypassing TLS validation.
- Confirm ngrok account Full Capture is off and review any log exports. Local inspection being disabled is not an audit of cloud settings.
- Recheck the unused future_trial contribution, database identity and restrictive policy immediately before activation.
- Resolve or explicitly review the deferred exposed-ngrok-token concern before any remote launch; local preparation does not revoke it.
- Separate approval to start the restricted endpoint, test denied public routes, register Test Mode callbacks, permit provider requests and perform exactly one virtual PHP 10.00 checkout with the synthetic faculty account. No real cards, bank credentials or live channels.

Provider account eligibility, channel availability for PHP 10.00, and actual key permissions cannot be proven offline. Do not change the amount silently if the selected test channel rejects it. Stop and report any mismatch or provider failure.
