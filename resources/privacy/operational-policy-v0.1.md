> PROPOSED INTERNAL POLICY - not approved and not operational instruction. Version 0.1; 9 October 2026. No automatic deletion is enabled by this document.

## Governance and access - proposed

An approved operator should maintain a named controller/processor register, accountable policy owner and reviewer roster. Access should follow an approved job-purpose matrix and existing server permissions. Proposed owners are the system administrator for account operations, Treasurer for collection processing, authorized announcement editors for publications, and an independently confirmed privacy lead for privacy review. These are responsibilities to approve, not appointments.

Access requests, role changes, exceptional exports and offboarding should be authorized and recorded. Existing deactivation and session/trust revocation can support offboarding. Periodic access reviews are proposed quarterly and on role changes; no completed reviews or approved frequency are claimed. Avoid shared accounts. Hosting/database/backup privileges require separate oversight from portal roles.

## Proposed retention schedule for decision

No duration in this table is approved. Expiry settings currently in code are not retention periods. Until a lawful schedule is approved, do not automate blanket deletion or retain everything indefinitely without review.

| Category | Proposed trigger and decision | Implementation status |
| --- | --- | --- |
| Identity/profile records | Review when membership/employment or service need ends; retain only fields needed for approved financial/audit obligations; decide exact period with controller | No approved interval; deactivation is available, erasure workflow is not. |
| Profile photos and optional biography/contact | Remove or replace when no longer necessary or a valid request is approved; confirm public-cache and backup handling | Old photo replacement cleanup exists; end-to-end erasure unverified. |
| Payment records, receipts and reference history | Retain under an approved financial/records schedule, considering disputes, accounting obligations and legal holds; do not invent a universal number of years | No approved interval; related-history restrictions exist. |
| Proof files and verification notes | Assess a shorter evidence period after reconciliation/dispute closure where legally permissible; exact duration and minimization need approval | No scheduled purge or case-based disposal implementation. |
| Sessions, tokens, trusted devices | Preserve validity limits; separately approve removal of expired rows and associated audit data | Local session 120 minutes, default trust 20 days, password reset 60 minutes, temporary password 48 hours are validity settings only. |
| Security/audit logs | Establish a justified investigation window and access limits; assess shorter retention of detailed IP/browser data | No general purge or immutable archive verified. |
| Notifications and announcements | Review after purpose/publication ends; remove unnecessary personal content while retaining required records | Read/expiry controls do not establish physical erasure. |
| Export files, support mail, queue errors, backups | Identify custodians and copies; align with source schedules and backup rotation; define hold exceptions and restoration safeguards | Operators, locations, actual schedules and disposal evidence unresolved. |
| Policy acknowledgment evidence | Retain the exact published policy versions, digest and associated event for an approved accountability period | Gate implemented but inactive; no approved period. |

## Security operations - proposed

Before deployment, verify HTTPS and secure session cookies; appropriate service permissions; key separation, recovery and rotation; protected backups; restore testing; private-file delivery; dependency updates; and configuration review. Require documented evidence rather than a checkbox assertion. Apply purpose-based logging and restrict secret/personal-data exposure in mail and exception logs. Review uploaded proof risks and an appropriate malware-scanning process before claiming scanning.

An incident procedure should identify responders, preserve proportionate evidence, contain access, assess affected data and harm, evaluate applicable reporting obligations, and communicate through approved contacts. Applicable thresholds, recipients and deadlines must be confirmed by a qualified reviewer; no breach-notification promise is made by this draft.

## Privacy requests and specific consent - proposed

Approve a real contact and intake process before publication. Record requests with minimal necessary details; verify identity proportionately; assess rights and lawful limitations; document decisions and escalation; and provide approved response/complaint information. This feature does not yet implement a rights-request ticketing service.

Where a specific processing purpose requires consent, design a separate, freely assessed purpose-specific choice with suitable information and withdrawal handling. Do not bundle it into login, a Privacy Notice receipt acknowledgment, or Terms acceptance. Do not treat unchecked acknowledgment as permission to ignore applicable rights.

## Disposal and holds - proposed

An approved disposal decision should identify the dataset, purpose expiry, retention rule, dependencies, outstanding dispute/hold, authorized reviewer and affected copies. Use tested application-aware procedures; do not bypass financial foreign-key protections or destroy encryption keys blindly. Include public files, private proofs, exports, logs, caches and backups. Validate the outcome and keep a minimal disposal record without copying erased personal data.

For backups, approve rotation and a process preventing erased data from silently reappearing after restoration. Exceptions and holds should be documented, access-restricted and re-evaluated. No secure-wipe, cryptographic-erasure or backup-destruction capability is claimed as implemented.

## Approval and review

The accountable operator, privacy/legal reviewer and records/finance owner should approve the final schedule, responsibilities and operating instructions. Reassess after material changes, new providers, incidents or new processing purposes. This proposal cannot authorize destructive maintenance or deployment changes by itself.
