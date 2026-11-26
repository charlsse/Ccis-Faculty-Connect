# First sandbox trial: private setup only

Preflight reviewed October 18, 2026. No tunnel or provider API request is authorized by this checklist. Do not register a webhook or click Test & Save yet.

## Verified locally

- Synthetic database is `ccis_xendit_test_20261018_111928`, port 3307, recovery mode zero. All three users match the synthetic fixture identities; trusted devices count is zero. This verifies the known fixture identities, not a forensic review of every free-text field.
- All 23 non-session tables in the normal recovered database and both preserved recovery schemas match their saved hashes. Normal `.env` is unchanged.
- Gateway, tunnel, and remote-request flags remain false. Sandbox mode is configured; Secure and HttpOnly cookies are true, SameSite is lax.
- ngrok configuration passes its offline check. Upstream is only `127.0.0.1:8002`. Policy allows only POST `/webhooks/payments/xendit` and GET `/payments/xendit/return`; other requests receive 404.
- Local inspector is disabled (`web_addr: false`), inspection storage disabled (`inspect_db_size: -1`), remote management disabled, agent logging warn. Cloud-side account logging/capture settings still need account review; local settings alone cannot prove cloud retention is disabled. Never put tokens or credentials in URLs, request logs, or dashboard screenshots.
- Recurring database backup task reports last result zero. Existing backups were not deleted or changed; no new restore drill was performed in this preflight.
- No ngrok/cloudflared process was found.

## Private account setup you must complete

Use this existing protected directory only:
`C:\Users\Charles\FacultyConnect-Sandbox\phase2-20261018`

Open `runtime.env` locally in an editor. Do not paste its contents into chat or enable editor cloud sharing. Preserve all existing database, APP_KEY, TLS bridge, and session settings. Store all newly supplied secrets here only, not in shell commands, ngrok YAML, reports, or repository files.

1. Sign into your ngrok dashboard privately. Locate **Your Authtoken** and the already assigned development hostname under Domains. Do not create/start an endpoint. Add `NGROK_AUTHTOKEN` to `runtime.env`; do not run `ngrok config add-authtoken`, which persists it elsewhere. During a later approved launch, pass it through the ngrok child process environment, never command arguments. ngrok documents environment-based authentication in its [official example](https://ngrok.com/download/docker).
2. In the protected `ngrok.yml`, replace only `https://pending.invalid` with `https://YOUR_ASSIGNED_HOST`. Preserve upstream and the entire restrictive policy. Set `APP_URL=https://YOUR_ASSIGNED_HOST` and `TUNNEL_HOST=YOUR_ASSIGNED_HOST` in `runtime.env`. Use the hostname exactly, without a path or port. No secret belongs in that YAML.
3. Sign into the Treasurer-controlled Xendit merchant account and select **Test Mode**. In Settings > Developers > API Keys, create a Test Mode secret key with only necessary money-in permissions; no payouts. Save it privately as `XENDIT_SECRET_KEY`. The implementation expects `xnd_development_`; do not substitute a live key. Follow [Xendit's official setup instructions](https://docs.xendit.co/docs/payments-integration-setup).
4. Obtain the account's webhook verification token privately and set `XENDIT_CALLBACK_TOKEN` (not `XENDIT_WEBHOOK_TOKEN`). Do not register a URL or click Test & Save. Xendit authenticates callbacks using [x-callback-token](https://docs.xendit.co/docs/handling-webhooks).
5. Obtain the exact merchant business ID from verified account details or Xendit support. Set `XENDIT_BUSINESS_ID`; do not infer it from the display name. Confirm with the Treasurer that this exact merchant is their intended account, then set `XENDIT_TREASURER_ACCOUNT_CONFIRMED=true`. This is an operator confirmation; API verification remains deferred. No bank/card credentials are needed.
6. Keep `PAYMENT_GATEWAY_ENV=sandbox`, `PAYMENT_GATEWAY_ENABLED=false`, `TUNNEL_ENABLED=false`, and `FC_SANDBOX_REMOTE_APPROVED=false` throughout setup. Do not alter the normal application `.env`.

No account authentication or merchant ownership has yet been verified: token, key, callback token, business ID, and hostname are currently missing. Inform the assistant only that private entry is complete; never send the values.

## Trust the verified localhost certificate

Certificate SANs are localhost and 127.0.0.1. Validity: October 18, 2026 09:25:02 UTC through November 17, 2026 09:25:02 UTC. It is not yet trusted by the current Windows user.

Verified certificate SHA-256:
`CF19A3F95AD6C4DE8EBA44D308929AE101FBAC5853681F12127C367A6780E75E`

Run the following in your own PowerShell session. It hashes the decoded certificate, refuses a different fingerprint, and imports only the public certificate into your current user's trust store. Do not import the private key, disable TLS verification, or bypass browser warnings.

```powershell
$certPath = 'C:\Users\Charles\FacultyConnect-Sandbox\phase2-20261018\localhost.crt'
$cert = New-Object System.Security.Cryptography.X509Certificates.X509Certificate2($certPath)
$sha = [System.Security.Cryptography.SHA256]::Create()
try { $fingerprint = ([BitConverter]::ToString($sha.ComputeHash($cert.RawData))).Replace('-', '') }
finally { $sha.Dispose() }
if ($fingerprint -ne 'CF19A3F95AD6C4DE8EBA44D308929AE101FBAC5853681F12127C367A6780E75E') { throw 'Certificate mismatch; stop.' }
if ($cert.NotAfter -le (Get-Date)) { throw 'Certificate expired; stop.' }
Import-Certificate -FilePath $certPath -CertStoreLocation Cert:\CurrentUser\Root
```

Browse only `https://localhost:8443` for synthetic login. Recheck trust and cookie protections before trial; after certificate expiry obtain and independently verify a replacement. Public access must never permit login or dashboards.

## Before requesting trial approval

Run the safe metadata-only check from the project directory:

```powershell
$env:FC_SANDBOX_CONFIG = 'C:\Users\Charles\FacultyConnect-Sandbox\phase2-20261018\runtime.env'
& 'C:\xampp\php\php.exe' tools/sandbox/private-preflight.php
```

This performs read-only database checks and prints presence booleans, never credentials. A matching key prefix is not remote proof of key validity or account ownership. Review the assigned hostname, account capture/logging settings, certificate trust, policy and unused `future_trial` contribution again before enabling anything. Existing local processes may require a controlled restart to load newly entered environment values; no public activation is part of setup.

After these blockers are resolved, request separate explicit approval for starting the restricted ngrok endpoint, registering Test Mode webhooks `payment_session.completed` and `payment_session.expired`, allowing provider verification requests, and executing exactly one **PHP 10.00 virtual Test Mode payment** against the synthetic faculty account's unused `future_trial` contribution. Webhook URL will be `https://YOUR_ASSIGNED_HOST/webhooks/payments/xendit`; read-only return URL is `https://YOUR_ASSIGNED_HOST/payments/xendit/return`.

Validate public denied routes before initiating any payment. Use no real bank details, cards, or live channels. Confirm settlement only from verified captured provider state; a browser return cannot mark paid. Stop the tunnel and disable all three activation flags after the approved trial.
