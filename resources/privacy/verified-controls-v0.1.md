> INTERNAL DRAFT - implementation evidence, not a certification or completed operational audit. Version 0.1; reviewed 9 October 2026.

## Verified in source / local metadata

| Control | Evidence | Important limit |
| --- | --- | --- |
| Administrator-issued accounts and onboarding | AuthController, OnboardingController, FacultyMemberController; no public register route | Organizational authority and eligibility rules still need approval. |
| Password hashing and validation | User password hash cast; Hash usage; onboarding/profile rules require at least 12 characters with mixed case, numbers and symbols | Does not verify users' security practices or mail transport. |
| Two-factor and recovery controls | TwoFactorService verifies TOTP freshness and single-use recovery codes; middleware checks completed authentication | Trusted-device feature can skip the authenticator challenge under its existing conditions; password still required. |
| Session/trust invalidation | SessionManager, TrustedDeviceService, security_version; ownership checks for self-service revocation | Deployment cookie security, TLS and runtime configuration must be checked separately. |
| Selected field encryption | User contact/bio/TOTP/recovery; Payment reference/notes; WaterMember waiver reason; WaterSubmission references/notes; WaterReceipt snapshot/reference use encrypted casts | Names, emails, many metadata columns and files are not covered by those casts. No claim of full-database, disk or backup encryption. |
| Reference and token protection | DataProtectionService uses normalized HMAC lookups; trust tokens hashed; code validates lookup-key material | Keys, rotation, key custody and recovery remain operational responsibilities; not verified here. |
| Server authorization | Auth/onboarding/2FA middleware, role and permission middleware, water record policies and ownership checks | Application controls do not constrain a privileged database, hosting or backup operator. |
| CSRF and validation | Existing POST/PATCH/DELETE forms include CSRF; controllers validate input/upload types and size; GET documentation routes do not mutate policy state | File-type validation is not antivirus/malware scanning. |
| Private proof and QR delivery | WaterPaymentController/WaterCollectionController authorize private storage access; proof responses set no-store/nosniff, PDFs download as attachments | Profile/announcement photos use public storage. Direct hosting/storage misconfiguration and exported copies were not audited. |
| Security audit detail filtering | AuditLogger removes keys matching sensitive categories before recording selected event details | Key filtering is not universal personal-data redaction. Events retain identifiers, IP and browser agent. No immutable log or SIEM claim. |
| Gateway verification in code | PayMongo adapter restricts test configuration, checks webhook signature/time and retrieves provider state; server-side totals/references are checked | Disabled and optional table absent locally. This is code capability, not proof of live operation or PCI compliance. |
| Documentation boundary (new) | Published routes require an approved public document with matching digest, completed checks, effective date and no open items; internal routes require admin role plus controller authorization | Repository/host operators can change code; approval evidence must be genuine and independently reviewed. |

## Not verified or not implemented as a policy

There is no approved organization-wide processing register, retention/disposal schedule, incident-response roster, privacy-request procedure, processor contract register, data-transfer assessment, recurring access-review record, documented backup/restore drill, security training record, penetration-test attestation, or ISO certification supplied for this review. Absence of evidence in this audit is not a claim that every external process is absent; those items require confirmation.

No automatic general purge, immutable audit archive, formal deletion/hold workflow, malware scan, or deployed security monitoring was identified in the reviewed application code. No purpose-specific consent workflow is asserted. The rights-request process remains a proposal, not a working case-management feature.

## Priority review items

1. Establish the accountable controller, operator, authorized approver and real privacy contact.
2. Review public profile-photo storage and public site/announcement images.
3. Verify deployed HTTPS, Secure cookies, session protection, key custody, server/storage access and backup restoration. Local Secure cookies and session payload encryption were disabled; do not mistake local defaults for a secure production deployment.
4. Approve financial-record/proof retention, export handling, legal holds and disposal across backups.
5. Review SMTP and Google Fonts recipients/locations; assess optional providers before enabling integrations.
6. Approve incident assessment, escalation, legally required notifications and a proportionate rights-request procedure.

## Testing is scoped evidence

Existing automated security tests exercise application authorization, authentication, session/trust handling, selected encryption and payment invariants with isolated data. They are not a legal opinion, institutional authorization, independent audit or proof of production operations. Refer to the implementation handoff for the exact test run and known pre-existing test failures.
