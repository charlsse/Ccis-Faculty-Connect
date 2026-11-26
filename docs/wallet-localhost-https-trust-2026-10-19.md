# Wallet localhost HTTPS trust — October 19, 2026

The port 8444 certificate was valid but absent from Windows trust. Port 8443 uses a different certificate that was already trusted for Charles. Trust is attached to the certificate, not automatically shared by localhost ports.

## Verified identity

- Port 8444: Node PID 29252, `C:/Program Files/nodejs/node.exe`, running `tools/wallet/local-https.cjs` with the wallet's protected environment. Listener: 127.0.0.1 only; upstream unchanged at 8003.
- Port 8443: Node PID 4828, running the existing `tools/sandbox/local-https.cjs`. No changes made to that service.
- Port 8444 live certificate matches `C:/Users/Charles/FacultyConnect-Sandbox/wallet-20261018/localhost.crt`.
- Subject and issuer: CN=localhost; self-signed, one-certificate chain. SANs: DNS localhost, IP 127.0.0.1.
- Validity in Asia/Manila: October 18, 2026, 23:56:26 through November 17, 2026, 23:56:26.
- SHA-256: `5a2fbfafd72d708ec6e2d6923af011e42f8d949deec0b44b02f6762410ed50fd`.
- Existing port 8443 SHA-256: `cf19a3f95ad6c4de8eba44d308929ae101fbac5853681f12127c367a6780e75e`; valid October 18, 2026, 17:25:02 through November 17, 2026, 17:25:02 Asia/Manila.

## Fix and validation

Before import, Windows curl failed with SEC_E_UNTRUSTED_ROOT. OpenSSL verified the live chain and localhost hostname using the pinned public certificate; the live and disk SHA-256 fingerprints matched.

Imported only that `.crt` into `Cert:\CurrentUser\Root`. The certificate contains no private key. The machine-wide store, existing 8443 certificate, services, configuration, credentials, database connections and payment logic were not changed. No replacement certificate or service restart was needed.

Windows curl now validates https://localhost:8444/login using its default trust store and returns HTTP 200. No insecure flag, custom trust override or hostname-verification bypass was used for the final Windows test. The requests only accessed the synthetic login page; they did not authenticate or initiate transactions.

For repeat verification, compare the public certificate fingerprint with the value above before importing it via Windows Certificate Import Wizard into Current User → Trusted Root Certification Authorities. Never import `localhost.key`. Existing trust need not be imported again.

## Chrome verification

Automated Chrome and Windows computer-use initialization both failed with `failed to write kernel assets ... os error 3`. On October 19, 2026, the user confirmed Chrome reports **“Secure connection; certificate valid”** after the requested browser check. This is user-assisted Chrome verification, not automated browser inspection.

The Windows TLS validation and user-confirmed Chrome check now both pass. The localhost:8444 HTTPS trust issue is resolved.
