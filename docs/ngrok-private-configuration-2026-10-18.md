# ngrok private configuration — October 18, 2026

Completed local configuration only. No tunnel, webhook registration, provider API request, or transaction was initiated.

## Results

- Protected `runtime.env`: changed only APP_URL and TUNNEL_HOST to the assigned `skid-porridge-snoring.ngrok-free.dev` hostname. All other lines compared unchanged, including database credentials, APP_KEY, TLS and Xendit settings.
- Protected `ngrok.yml`: replaced only the placeholder endpoint hostname. Upstream remains `http://127.0.0.1:8002`; port 8000 is not configured.
- `ngrok config check --config` passed against that private file.
- Existing policy remains byte-identical apart from the hostname: only POST `/webhooks/payments/xendit` and GET `/payments/xendit/return` escape the default 404 response. This is static/offline validation, not a live edge test.
- Local web inspection disabled, inspection storage -1, remote management disabled. No ngrok/cloudflared process found.
- Authtoken present privately; account authentication not remotely tested. No token copied into YAML, arguments, logs or this report.
- All three activation flags remain false. Secure/HttpOnly cookies remain true, SameSite lax.
- Read-only checks matched all 23 non-session tables in the recovered application database and both preserved recovery schemas. Normal `.env` matches its saved hash. Original damaged server was not modified or queried.
- Synthetic identity is `ccis_xendit_test_20261018_111928`, port 3307. Three known synthetic users and zero trusted devices were verified.

## Later environment-based authentication

`tools/sandbox/Stage-NgrokEnvironment.ps1` reads only the private runtime file and sets NGROK_AUTHTOKEN in the invoking PowerShell process for inheritance by a future ngrok child. It never starts ngrok. It was syntax-checked, not executed with the exposed token. Do not use it until the token is replaced. Clear the process variable with `Remove-Item Env:NGROK_AUTHTOKEN` after the later approved child launch. Never print environment variables or use shell tracing. A future launch must explicitly use the protected ngrok.yml and named synthetic endpoint; it still requires separate approval.

## Exposed credentials: activation blocker

The supplied IDE context exposed synthetic secrets. Treat them as compromised even though the synthetic database is loopback-only:

1. Revoke the exposed ngrok agent authtoken in the account dashboard and generate a replacement. Put the replacement only in protected runtime.env; never paste it into chat.
2. Rotate the synthetic scoped database user's password and update its private runtime configuration together.
3. Rotate the synthetic APP_KEY with a deliberate reset/re-encryption of synthetic encrypted 2FA data and invalidation of synthetic sessions. Blind key replacement can make the synthetic test accounts unusable.
4. Rotate FC_LOCAL_TLS_TOKEN in the synthetic environment and restart both local synthetic processes together.

These rotations were not performed because this request explicitly preserves those settings. Do not change the recovered normal application's key, users or database credentials. Xendit secret key/callback token remain absent and were not exposed; business ID and Treasurer confirmation also remain unset. Avoid attaching runtime.env as IDE context in future messages.

## Cloud-side capture review — operator action required

The signed-in account settings have not been inspected. Local agent settings cannot establish cloud-side capture status.

1. Sign into the intended ngrok account as an account administrator.
2. Open **Settings → Observability**, then locate **Full Capture**. Ensure it is disabled and save any change. Do not enable capture or replay for this trial.
3. Open **Traffic Inspector** and verify that full request/response bodies and headers are not being captured. Do not send a request merely to test this before activation approval.
4. Review any configured log exports and account-specific traffic policies; do not export callback headers/bodies or enable custom logging of secrets. Keep the existing local policy unchanged.
5. Report only that capture is disabled, or the setting labels if they differ; do not share token pages or captured payloads.

These steps follow [ngrok's Traffic Inspector documentation](https://ngrok.com/docs/obs/traffic-inspection): Full Capture is an account-admin setting under Observability. Metadata may still be retained when Full Capture is off; this is not a promise of zero logging.

## Before the first trial

Resolve exposed-secret rotations, enter Test Mode Xendit credentials privately, verify the exact Treasurer merchant ID, complete certificate trust, and confirm cloud capture settings. Re-run local preflight and verify the unused PHP 10.00 future_trial contribution. Obtain separate approval before starting ngrok, registering callbacks, making provider requests, or performing the single virtual transaction. Test denial of private public routes before any approved payment creation.
