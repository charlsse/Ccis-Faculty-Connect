# Synthetic credential rotation — October 18, 2026

Local rotation and verification completed. ngrok token replacement remains pending user action. No public activation, webhook registration, or Xendit API request occurred.

## Changes confined to the synthetic environment

Target: `C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018`, database `ccis_xendit_test_20261018_111928` on port 3307.

- Stopped only the identified synthetic PHP backend on 8002 and Node HTTPS bridge on 8443 before mutation; restarted both with the new settings. Normal port 8000 was not stopped.
- Rotated `fc_xt_1018111928@127.0.0.1` password using the isolated administrative account. New scoped connectivity passed; old password was rejected. No grants were broadened; reading the normal recovered database with the synthetic account remains denied.
- Rotated synthetic APP_KEY. Reviewed all encrypted casts in User, Payment, WaterMember, WaterGatewayAttempt, WaterReceipt and WaterSubmission. All 19 populated encrypted values decrypted successfully before mutation and were re-encrypted with plaintext equality checks. Existing synthetic receipt/submission data was preserved.
- Regenerated passwords and TOTP secrets for all three synthetic accounts. Reset their recovery-code arrays and last-used TOTP steps, cleared remember tokens and incremented security versions. Updated only their protected synthetic credential fixture file.
- Invalidated all eight existing synthetic database sessions. New test sessions are logged out after verification.
- Rotated FC_LOCAL_TLS_TOKEN; verified that the old bridge token receives 404. TLS certificate and private key were not replaced, and certificate verification remained enabled.
- Updated APP_KEY and bridge token in private migration.env for consistency; its separate migration-account password was unchanged. No migration was run.
- All other runtime settings remain unchanged. Secure/HttpOnly=true, SameSite=lax. Gateway, tunnel, and provider-request approval flags remain false.

## Verification

All **33 HTTPS checks passed** against https://localhost:8443 using the verified certificate. Coverage includes all three synthetic roles' password login, required TOTP, dashboards, security pages, collection/report permissions, Treasurer-only access, logout and unauthenticated dashboard rejection. TLS verification was not bypassed.

Postchecks confirm all test sessions logged out, the future_trial contribution unused/unpaid with no gateway attempt, and zero private Laravel error-level markers. No ngrok/cloudflared process was present.

All 23 non-session table hashes in the normal recovered database and both preserved recovery schemas match the preservation baseline. Normal `.env` remains unchanged. The original damaged database and recovery archives were not modified. Synthetic root ACL inheritance remains disabled.

Protected rotation evidence:
`C:/Users/Charles/FacultyConnect-Sandbox/phase2-20261018/rotation-20261018-105423-e0c536`

It contains pre-rotation configuration and affected synthetic row recovery material, result.json and postcheck.json. Keep it protected: old credentials are compromised recovery evidence and must never be reactivated. Current synthetic test credentials remain only in the protected runtime/fixture files. No secrets are included in this report.

Audit helpers added: tools/sandbox/rotate-private-credentials.php and tools/sandbox/rotation-postcheck.php. The rotation helper is a one-off mutation script, not a routine startup command; do not rerun blindly. Local bootstrap uses private cache paths and prevents provider requests while remote approval is false.

## Pending ngrok replacement and activation blockers

The user confirmed replacement is **not yet completed**. Current token is present with plausible syntax but unchanged; this is not authentication validation and does not make the exposed token safe.

1. Open [ngrok Authtokens](https://dashboard.ngrok.com/authtokens) privately.
2. Revoke/delete the exposed agent credential and create a replacement dedicated to this synthetic environment. Do not create an API key or public endpoint for this step.
3. Replace only NGROK_AUTHTOKEN in protected runtime.env. Do not put it in ngrok.yml, command arguments or chat. Close the file before replying to avoid IDE-context disclosure.
4. Report only that replacement is saved. The assistant can then check presence and syntax without remote authentication or revealing its value. Dashboard revocation remains an operator-confirmed action.

Also pending before activation: private Xendit Test Mode key/callback token, exact merchant ID and Treasurer confirmation, current-user certificate trust confirmation, and cloud-side Full Capture/log-export review. No real cards, bank credentials, payments or provider calls are needed for these local checks. A separate explicit approval is still required for the first PHP 10.00 virtual trial and restricted public tunnel.
