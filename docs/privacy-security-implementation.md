# Privacy & Security documentation implementation

Implemented 9 October 2026. All seven supplied documents remain internal drafts. No policy publication, institutional responsibility, legal compliance or ISO certification is asserted.

## Feature status

- `/privacy` provides public publication status and educational framework references. No draft notice or terms are publicly accessible.
- `/dashboard/privacy-security` separates published policies from administrator-only working documents. It is linked from Account Security and the account menu; the public status page is linked from authentication screens.
- Draft routes require completed authentication and the administrator role, with an additional controller check. Responses prohibit caching; draft responses discourage indexing. Markdown strips embedded HTML and unsafe links.
- Draft files live outside Laravel's public directory. An additional `.htaccess` denies direct Apache access when this repository is served below the XAMPP document root. The tested direct document URL returned HTTP 403. Production should use Laravel's `public` directory as its document root.
- Publication uses a reviewed code release, with approval evidence, completed review checks, effective dates, no unresolved items and a matching SHA-256 digest. There is no in-app publication button.
- Privacy Notice acknowledgment and Terms acceptance are implemented separately in existing administrator-issued account onboarding. They remain **inactive**. Both complete approved documents and explicit activation are required. Successful future acknowledgment records exact versions and hashes atomically with password replacement; it never records purpose-specific consent. No new public registration route or database migration was introduced.

## Deliverables

1. [Proposed Privacy Notice](../resources/privacy/privacy-notice-v0.1.md)
2. [Proposed Terms of Use](../resources/privacy/terms-of-use-v0.1.md)
3. [Actual Data Inventory and Processing Register](../resources/privacy/data-inventory-v0.1.md)
4. [Verified Controls and Review Gaps](../resources/privacy/verified-controls-v0.1.md)
5. [Proposed Retention, Access, Security and Disposal Policy](../resources/privacy/operational-policy-v0.1.md)
6. [Security & Privacy Framework References](../resources/privacy/framework-references-v0.1.md)
7. [Approval and Publication Register](../resources/privacy/approval-register-v0.1.md)

## Audit scope and findings

Reviewed application schema, models, forms, authentication/onboarding, authorization, storage routes, notifications, logging, framework storage and integrations. Queried only local application schema/runtime configuration metadata, not personal record contents. The local database contains 24 application tables; the optional PayMongo table is absent and its integration is disabled. Local settings are not evidence of production settings.

Existing controls include password hashing, TOTP/recovery handling, role/ownership authorization, CSRF protection, session/trust revocation, selected encrypted fields and protected payment-proof/QR delivery. Profile and announcement images are publicly stored. Many identity/metadata fields and uploaded files are not encrypted by model casts. No approved retention schedule, disposal procedure, operational incident process or backup-restoration evidence was established.

The institutional reference is [UMak's supplied CDPRM URL](https://www.umak.edu.ph/centers/cdprm/). Direct retrieval returned 403 and indexed office naming differed during this review. The supplied officer designation and older `dprms@umak.edu.ph` address are explicitly unconfirmed for FacultyConnect; neither is presented publicly as its responsible contact. Institutional branding does not establish approval or responsibility.

Educational references cover ISO/IEC 27001:2022, ISO/IEC 27002:2022 and ISO/IEC 27701:2025, with the exact requested no-certification disclosure. The Philippine Data Privacy Act is a reference, not a claim that the application satisfies all legal obligations.

## Remaining approvals

Confirm the controller/operator, institutional arrangement, authorized approver and responsible contact. Review processing purposes and legal bases, public image visibility, recipients and hosting, retention/legal holds, disposal and backups, rights/incident procedures and final notice/terms. Obtain qualified privacy/legal and accountable-owner review before publishing. Follow the approval register for immutable version files, evidence and activation; never approve incomplete text for a demonstration.

## Verification

The five new feature tests pass with 105 assertions: administrator boundaries, draft publication denial, fail-closed approval checks, inactive onboarding behavior, separate acknowledgment validation, stale-version rejection and exact-version audit records.

The full suite ran 141 tests with 1,304 assertions: 139 passed and two existing wording assertions failed (`AccountWorkflowTest` expects “Trust this device for 20 days”; `WaterCollectionTest` expects “Report & CSV export”). Those unrelated text expectations were not changed.

Browser verification passed 80 responsive/theme checks using isolated synthetic QA accounts: light/dark themes, widths 320–1920 px, table containment, role boundaries, public draft denial and sticky glass headers over scrolling content. A workspace table minimum-width conflict was corrected for the inventory on small screens. Screenshots of desktop documentation and mobile authentication/inventory were visually reviewed. Blade templates also compiled successfully. The reproducible check is `tests/Browser/privacy-documentation.cjs`; its private artifacts are under `storage/app/private/workspace-glass-qa`.
