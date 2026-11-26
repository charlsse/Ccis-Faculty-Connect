# Treasurer-centered FacultyConnect payments

Updated September 28, 2026. This architecture supersedes the earlier PayMongo-first presentation and shared Admin/Treasurer financial permissions.

## Primary workflow

Water Contribution and Other Collections now share the existing collection, assignment, submission, receipt, notice and audit architecture. The Treasurer creates a collection, selects its receiving account and assigns members. Published payment terms remain immutable.

An assigned faculty member or officer selects Pay Now. Checkout opens the Treasurer QR dialog, with the stored receiving account, exact assigned amount, QR download and payment instructions. I Have Paid closes the dialog and opens the reference/proof form. Submission creates Pending Treasurer Verification, not a paid record. The Treasurer compares the proof with the actual receiving-account transaction before approving or requesting correction. Approval updates the obligation, receipt, history, report and in-app notification together.

The popup supports Escape, Close, reopening and keyboard focus. Proof input supports JPEG, PNG and PDF, maximum 5 MB, with image preview/replacement. The server checks ownership, published status, exact amount, reference reuse and prior payment state. Private proofs and receipts enforce access checks. PDF proof files download rather than execute in the app origin.

## Financial ownership and RBAC

| Role | Financial access |
| --- | --- |
| Treasurer | Receiving-account configuration, collection management, review/correction/rejection, cash recording, waivers and reports; can also pay their own assigned contribution |
| Administrator / President | Read-only oversight of collections, members, transactions, proofs, receipts, reports/CSV and audit; cannot create/change receiving accounts, approve/reject, record cash, waive, change collections or write legacy payment records |
| Faculty / assigned officers | Pay their own assigned contributions, view their own history, proof, receipts and notices |
| Unassigned members | Cannot read another member's private payment or submit against their assignment |

`payments.view` grants oversight; `payments.manage` belongs only to Treasurer. HTTP routes, controller/service authorization and templates enforce the split. An Admin can still pay a personally assigned contribution as a payer, which does not grant financial management rights.

The existing active QR file is readable. Its record was created under the former Admin permissions; the user explicitly confirmed that the receiving account is controlled by the Treasurer. Its destination was preserved. Future account creation/modification is Treasurer-only. The account form requires confirmation of Treasurer control. The application cannot independently verify legal ownership of an external bank/wallet account.

## Database changes and preservation

Applied only the additive `2026_09_28_150000_add_collection_category.php` migration to the existing application database. Existing collections default to Water; new collections may choose Other. No amounts, assignments, receiving destinations, historical submissions or receipts were changed.

The existing `water_*` table and route names remain for compatibility; category does not create a second payment engine. New Other Collection receipts use FC-CLUB identifiers; existing Water receipt identifiers remain unchanged. The older `payments` ledger is retained under Historical Payment Records. Existing ledger entries were not automatically converted into new payable obligations, which could duplicate dues or lose provenance. New collections should use the unified collection editor.

## PayMongo isolation

PayMongo remains disabled, retained as an experimental future integration. Disabled online options and setup banners are absent from the normal faculty flow. If explicitly enabled later, the option is labeled Experimental / Test Mode and is secondary to Treasurer QR.

Normal collection creation, QR display, proof upload, Treasurer review, receipt generation, history, reporting and in-app notifications require none of the PayMongo keys, webhook secrets, sandbox flags, merchant verification, connectivity or gateway tables. No PayMongo test database is a prerequisite for the application. The existing development instance has no gateway attempt table and the gateway is disabled.

The optional migration was moved to `database/experimental/paymongo/2026_09_28_120000_add_water_gateway_attempts.php`. Normal `php artisan migrate` does not discover it. It creates provider-attempt reservations and allows provider receipts without a human verifier. Existing deployments that already applied it can retain their table and migration history; do not drop financial history to disable the feature. Gateway tests explicitly load the optional migration.

The manual engine retains one protective compatibility check: if an actual unresolved online attempt was previously created, that particular contribution remains reserved until reconciled, even if the feature is later disabled. This prevents double payment; a missing/invalid gateway configuration does not block ordinary manual contributions. No provider call is made by normal QR/proof operations.

## Verification

Final backend regression result: **135 tests, 1,199 assertions passed**. Final browser result: **260 layout checks, no browser errors**, including both full payment workflows.

Automated tests cover both categories end-to-end with no gateway table, no secrets and disabled gateway flags, with external HTTP requests prohibited. They verify proof/reference submission, pending state, Treasurer approval, receipt, reports and notices. Additional direct-request tests deny Admin financial writes, including account changes, approval/rejection, cash, waivers, collection changes and legacy ledger changes, while permitting oversight.

Browser QA uses synthetic accounts and payments, separate from real application records. Both Water and Other workflows pass login/2FA, Pay Now dialog, Escape/reopen, proof upload, pending state, Treasurer approval, faculty receipt and Admin report/receipt access. Ten screens were checked at 13 widths (320–1920px), in light/dark mode: 260 layout checks. Screenshots and results are in `storage/app/private/treasurer-qr-qa`. QA images are synthetic, not scannable payment QRs. No real payment or email was sent.

Run `php packages/phpunit/phpunit/phpunit` for backend regression tests. The guarded `tools/treasurer-qr-*` helpers and `tests/Browser/treasurer-qr.cjs` run local browser fixtures; these are development verification tools, not application setup requirements.

External bank settlement was not exercised. The Treasurer must verify actual receiving-account transactions. In-app notices are transactional; optional email delivery still depends on the separately configured mail transport and Brevo authorization.

## If sandbox testing is requested later

Only after an explicit request: configure a separate synthetic-data sandbox, PayMongo test secret, HTTPS webhook and its signing secret, apply the optional migration with `php artisan migrate --path=database/experimental/paymongo`, then enable the sandbox flags. Verify an actual provider simulator/webhook round trip before considering the experiment operational. No live mode is implemented. Never scan PayMongo test QR codes using a real banking app. See the [sandbox integration guide](paymongo-sandbox-integration.md) for provider-specific details.

None of these sandbox steps is needed for the Treasurer QR payment workflow.
