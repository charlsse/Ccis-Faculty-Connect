> INTERNAL DRAFT - publication blocked. Version 0.1; 9 October 2026. No final policy or institutional approval is recorded.

## Approval register

| Decision | Status | Required evidence |
| --- | --- | --- |
| FacultyConnect operator/controller and any processors | Requires review | Written scope, authority and allocation of responsibilities |
| University affiliation, sponsorship or delegation | Not verified | Formal arrangement; do not infer from branding or office references |
| Responsible privacy contact | Not confirmed | Current designation, monitored contact and express project responsibility |
| Processing purposes, lawful bases and sensitive-data assessment | Requires review | Per-purpose decision and minimization review tied to the inventory |
| Public photo/media visibility | Requires review | Approved necessity, scope and handling of permissions/rights |
| Recipients, hosting/mail operators, locations and transfer safeguards | Requires review | Current service inventory, contracts and deployment evidence |
| Retention, holds, exports, backup handling and disposal | Not approved | Dataset-specific approved rules and tested procedures |
| Privacy requests, complaints and incident response | Proposed only | Authorized owner, intake/escalation process and applicable timelines |
| Privacy Notice and Terms of Use | Draft 0.1 | Qualified legal/privacy review and accountable-owner approval |
| ISO certification | None verified | No claim permitted on present evidence |
| Onboarding acknowledgment | Implemented, inactive | Both approved policy versions published; explicit activation decision |

## Publication mechanism

The registry is `config/privacy-documents.php`. All documents currently remain draft. Only Privacy Notice and Terms are candidates for public policy publication; the inventory, operational proposals, control gaps and approval notes are always internal administrator material. Internal documents must never be moved into the public storage disk.

Publication is a reviewed code release, not an in-app Publish button. For each finalized public document, create a new immutable version file and review its exact contents. Set a non-draft version, public audience, published status, no open items, reviewer identity, approval evidence reference, approval/effective dates, and SHA-256 of the approved file. Record true review decisions for controller, contact, processing, retention, legal and owner checks only after evidence exists. The application fails closed when metadata is absent, dates are future, reviews are incomplete, the file changes after approval, or the document is internal.

The metadata is an implementation gate, not independent proof that a review occurred. Keep the signed/offline evidence in an appropriately protected approval record, archive each approved source file, and verify that publication authorization is genuine. Never mark checks true simply for a demonstration.

## Acknowledgment release

The flag `acknowledgment_enabled` stays false. Even if enabled, missing/unapproved documents leave the mandatory fields inactive. Once both valid documents are published and activation is approved, the existing account-onboarding password form shows separate Privacy Notice receipt and Terms acceptance checkboxes. Server validation requires those choices and the exact displayed versions/hashes. A stale document version requires the person to reload and review again. The audit event is stored with password replacement in the same database transaction.

There is no public registration route. This integrates at the existing account-setup stage rather than creating a new registration or authorization path. An administrator creating an account cannot acknowledge on behalf of its recipient through this feature. No purpose-specific consent is inferred or collected. Existing accounts are not silently treated as having accepted anything; a later re-acknowledgment campaign requires a separately reviewed design.

## Demonstration guidance

Demonstrate draft previews while signed in as an authorized administrator. Show the published-policy area as awaiting approval. Show framework references with the explicit no-certification disclosure. Explain the actual technical controls separately from proposed operational controls. Use synthetic accounts only. Do not turn on publication or acceptance flags for a capstone demonstration with these incomplete texts.
