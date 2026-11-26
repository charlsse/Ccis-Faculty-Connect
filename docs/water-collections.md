# Water Collections and QR Payment Management

## Implemented behavior

FacultyConnect now has a dedicated Water Collections module at `/dashboard/water`, linked from the shared desktop/mobile navigation and dashboard. The existing Collections & Payments module, account invitations, login, mandatory 2FA, profile and role system remain in place. Administrator / President and Treasurer reuse the existing `payments.manage` permission. Secretary and legacy Staff receive no financial management privileges.

Officers create a draft, select all active faculty (including officers) or specific active members, then publish it. Assignments are unique and initially unpaid. Only assigned members see published, closed or archived collections. Financial terms and member assignments become immutable after first publication, including after unpublishing. Draft assignment editing is permitted only before first publication. Unpublishing is blocked after submissions or waivers. Closing stops new faculty submissions but allows outstanding verification and authorized cash/waivers. Archiving requires every obligation to be paid or waived; history remains available. There are no collection deletion routes.

Official QR destinations are versioned. Saving a replacement account makes it the sole active default, preserving old destinations for their existing collections. Officers may activate/deactivate accounts. Uploading a new version is the supported correction process for account labels, names, masked numbers, instructions and QR images. Do not enter full wallet numbers, MPINs, OTPs, passwords or wallet credentials.

Faculty pay outside FacultyConnect, then submit an exact amount, reference, local payment date/time, optional note and private proof. The form presents a confirmation summary. Pending obligations reject repeat submissions. Officers compare the reference and proof against actual receiving-account transaction history and approve, reject or request correction. Rejected attempts and proofs are retained; new attempts link to the previous attempt. A corrected attempt may retain its reference on the same obligation. A reference cannot be reassigned to another obligation, even after rejection.

Approval updates the obligation, submission, audit entry, in-app notice and unique receipt in one database transaction. Receipt numbers use the database-allocated submission ID, such as `FC-WATER-202609-000002`; gaps are expected. Receipt content snapshots the member name, collection title/period and officer name so later profile edits do not rewrite historical receipts. Receipts are printable and downloadable as PDF only for approved payments. Cash recording uses the signed-in officer as the receiving officer and produces the same verified receipt. Waivers require an officer and reason and never generate payment receipts.

**Static GCash / QR Ph images never confirm payment automatically.** The module has no payment-provider callback integration and does not claim one.

## Schema and migration

`database/migrations/2026_09_13_000000_create_water_collections.php` adds eight tables:

| Table | Purpose and constraints |
| --- | --- |
| `water_payment_accounts` | Versioned official destination and private QR path |
| `water_settings` | Singleton row lock and active default account ID |
| `water_collections` | Title, instructions, period, decimal amount, due date, destination, lifecycle and creator |
| `water_members` | Per-member decimal obligation; unique collection/user; paid and waiver metadata |
| `water_reference_claims` | Primary-key HMAC reference claim, permanently bound to one obligation |
| `water_submissions` | Immutable attempts, encrypted references/notes/reasons, private proof path; unique obligation/attempt; previous attempt link |
| `water_receipts` | Unique receipt number, unique obligation, unique submission; encrypted historical snapshot/reference |
| `water_notices` | User-scoped in-app messages and read state |

Existing `audit_logs` and `users` are reused. No existing payment rows or user roles are converted. Financial timestamps use `DATETIME` to avoid older MariaDB implicit `TIMESTAMP` default behavior; application timezone conventions are preserved and displayed. Monetary storage is `DECIMAL(12,2)` and amount equality uses integer cents. Foreign keys restrict deleting financial history.

The local MySQL/MariaDB database was backed up with the existing private backup command before migration. The migration is applied locally as batch 7. The initial MariaDB default-timestamp error was corrected and only verified-empty, partially created water tables were removed before retrying. No existing account or financial records were changed by testing. The private backup must remain private; do not publish it.

## Permissions and routes

Every route below also requires authentication, usable account status, completed onboarding and confirmed 2FA. The two policies are auto-discovered by Laravel. Officer routes enforce `permission:payments.manage` and controller/service authorization. Member proof/receipt checks reuse obligation ownership authorization.

| Route name(s) | Method / path after `/dashboard/water` | Access |
| --- | --- | --- |
| `water.index` | GET `/` | Own assigned history; officers see all |
| `water.create`, `water.store` | GET `/create`, POST `/` | Officer |
| `water.show` | GET `/{collection}` | Assigned member or officer |
| `water.edit`, `water.update` | GET `/{collection}/edit`, PUT `/{collection}` | Officer; never-published draft only |
| `water.transition` | PATCH `/{collection}/status` | Officer; controlled lifecycle |
| `water.accounts`, `water.accounts.store` | GET/POST `/accounts` | Officer |
| `water.accounts.activate` | PATCH `/accounts/{account}` | Officer |
| `water.accounts.qr` | GET `/accounts/{account}/qr` | Officer |
| `water.qr` | GET `/{collection}/qr` | Assigned member or officer; `download=1` downloads |
| `water.queue` | GET `/queue` | Officer; collection, status, faculty, submitted date, exact-reference filters |
| `water.submit` | POST `/members/{member}/submit` | Owner only; published, unpaid/rejected obligation |
| `water.submission`, `water.proof` | GET `/submissions/{submission}` and `/proof` | Owner or officer |
| `water.review` | POST `/submissions/{submission}/review` | Officer; pending only |
| `water.cash`, `water.cash.store` | GET/POST `/members/{member}/cash` | Officer |
| `water.waive` | POST `/members/{member}/waive` | Officer; required reason |
| `water.receipt` | GET `/receipts/{receipt}` | Owner or officer; verified only; `download=1` generates PDF |
| `water.report` | GET `/{collection}/report` | Officer; `download=1` exports CSV |
| `water.audit` | GET `/audit` | Administrator / President only |
| `water.notices.read` | POST `/notices/{notice}/read` | Notice owner only |

All mutations use CSRF-protected forms. Uploads and financial mutations have rate limits. Unexpected module errors return a safe failure page and log exception type/route only. Financial references and confidential notes are encrypted and hidden from normal model serialization. Audit records exclude references, proof contents and private notes. Uploads use random filenames on the existing private `local` disk. Proofs allow real JPEG/PNG/PDF with matching supported extensions, up to 5 MB. QR uploads allow real JPEG/PNG, up to 5 MB. PDFs are attachment-only, never embedded in the app origin. File responses have `nosniff`, sandbox CSP and private/no-store headers.

Reports include assigned amount, verified paid amount, method/reference, state, payment/verification dates, verifier and receipt. For unverified rows, method/reference/payment date describe the latest submission; the paid amount remains zero. CSV exports neutralize formula prefixes, including whitespace-prefixed formulas, and include totals. Expected amount includes waived amounts; outstanding excludes paid and waived obligations.

## Notifications and environment

In-app notices are written transactionally for publication, submission/resubmission, approval, rejection/correction and cash/waiver actions. The existing Laravel mail-notification API handles optional email. There was no existing database notification feed, so the module adds a scoped `water_notices` feed and links the notification icon to it.

Optional new variable: `WATER_EMAIL_ENABLED`. It defaults to false. Enable it only after the existing real mail transport has been verified. The allowlisted transports match the invitation workflow: SMTP, sendmail, SES, Postmark and Resend. Log/array transports are refused for module email. Mail is sent after commit; transport failure leaves approval and its receipt intact, records safe failure metadata and presents a warning. No payment proof, full financial details or credentials are placed in the email; it links to an authenticated collection page. There is no queued mail job or automatic email retry.

Existing relevant variable names: `APP_KEY`, `APP_PREVIOUS_KEYS`, `FACULTYCONNECT_LOOKUP_KEY`, `APP_TIMEZONE`, `APP_URL`, `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `SESSION_ENCRYPT`, `SESSION_SECURE_COOKIE`. Keep the existing encryption keys. The real `.env` was not edited or printed. Configure the intended timezone before recording live payments and keep it consistent. Ensure PHP `upload_max_filesize` and `post_max_size` allow the advertised 5 MB upload plus form overhead.

## Dependencies

Installed production dependency: `dompdf/dompdf` 3.1.6, constrained to `~3.1.0`, for downloadable server-generated PDFs. Composer resolved it against PHP 8.2.12; no Laravel wrapper is required. Remote fetching, embedded PHP and JavaScript are disabled. PDF inputs are escaped server templates and verified stored data. The maintainer's [repository](https://github.com/dompdf/dompdf) and [releases](https://github.com/dompdf/dompdf/releases) were checked before installation. Composer reported no known security advisories.

Transitive additions: `dompdf/php-font-lib` 1.0.2, `dompdf/php-svg-lib` 1.0.2, `masterminds/html5` 2.11.0, `sabberworm/php-css-parser` 9.4.0, `thecodingmachine/safe` 3.4.0. Existing production packages were not upgraded.

Private QA-only tools under `storage/app/private/water-qa/node_modules`: Playwright Core 1.63.0, PDF.js 6.3.289 and `@napi-rs/canvas` 1.0.9. These are not application runtime dependencies. PDF.js/canvas were used because Poppler was not installed. GD was unavailable, so PHPUnit uses a real small PNG fixture rather than GD-generated images. The text-only receipt PDF renders without GD.

## Setup and verification commands

```powershell
composer install
php artisan config:clear
php artisan facultyconnect:backup-database --label=pre-water-module
php artisan migrate
php artisan test
php packages/bin/pint --test app config database routes tests bootstrap/app.php
composer validate --no-check-publish
php artisan view:cache
php artisan route:list --path=dashboard/water
```

Then sign in as an existing Administrator / President or Treasurer, open Water Collections > QR accounts, add the verified official destination, create a draft, assign members and publish it. No real QR destination, live collection, or production test account is seeded by this module. Existing administrator setup remains `php artisan facultyconnect:setup-admin` when needed.

Isolated browser QA uses synthetic accounts in a separate SQLite database and a loopback-only server:

```powershell
npm.cmd install --prefix storage/app/private/water-qa playwright-core pdfjs-dist @napi-rs/canvas
php tools/water-browser-fixture.php
php -S 127.0.0.1:8091 -t public tools/water-browser-router.php
# In another terminal:
node tests/Browser/water-workflow.cjs
node tools/water-pdf-check.mjs
```

The fixture refuses to replace an existing QA database. Use a fresh isolated QA directory for a new run; do not point these tools at the application database. Synthetic credentials are generated randomly, stored only inside that private QA directory and are not default application login credentials. The browser test uses system Chrome. Stop the loopback server after testing. Never serve the repository root; serve only `public/`.

## Verification results and limits

- Complete PHPUnit suite: **84 passed, 667 assertions**, including all 67 pre-existing tests. Coverage includes roles, ownership, private files, real file validation, exact amounts, duplicate/reference claims, corrected attempt linkage, double approval, rollback on receipt failure, snapshots, PDF authorization, cash/waivers, archival, report totals, queue filtering, CSV injection, in-app notifications and mail-failure isolation.
- Migrations ran against isolated SQLite and the backed-up local MySQL/MariaDB database. The local water tables contain no test faculty/payment records.
- Laravel Pint, Composer validation, JavaScript syntax, Blade compilation and module route registration passed.
- Browser results and screenshot evidence are recorded privately in `storage/app/private/water-qa/browser-results.json` and PNG files. Browser automation exercises actual password/2FA login and the complete form workflow with separate faculty and officer contexts. **60 layout checks passed** at 360, 390, 768, 1024 and 1440 pixels, with no page overflow or unexpected console errors. The expected HTTP 403 from the negative proof-access test is excluded from unexpected console errors.
- Visual review includes the mobile payment form, desktop receipt and rasterized one-page PDF. The PDF's verified status and synthetic member name were also extracted and checked.
- The connected interactive browser was unavailable. Browser workflows were therefore automated in isolated headless Chrome, with manual visual inspection of screenshots; there was no physical-phone or touch-device test. Real GCash/QR Ph scanning, real payment-provider transactions and real SMTP inbox delivery were intentionally not tested. No real financial data was used.
- Concurrent multi-worker MySQL stress tests were not run. Row locks, transactions and database uniqueness are implemented, and duplicate/replay/rollback behavior is covered by tests. Consider a staging concurrency/load test before a large rollout.

## Deliberate boundaries and recommended follow-up

There is no public registration, automatic QR confirmation, payment reversal/refund, partial payment, reminder scheduler or automatic email retry. Corrections before approval use rejection/new attempt history. Paid records are immutable through the interface. Historical reference claims remain reserved even if rejected; exceptional reference-release or post-verification reversals would need a separately audited controlled process. Optional future improvements are provider integration, scheduled reminders, mail-delivery monitoring/retries, malware scanning and a documented financial retention policy. Preserve private storage and encryption keys in backups.

## Created files

- `database/migrations/2026_09_13_000000_create_water_collections.php`
- `app/Models/WaterCollection.php`, `WaterMember.php`, `WaterPaymentAccount.php`, `WaterSubmission.php`, `WaterReceipt.php`, `WaterNotice.php`
- `app/Policies/WaterCollectionPolicy.php`, `WaterMemberPolicy.php`
- `app/Services/WaterPaymentService.php`, `WaterReceiptPdf.php`
- `app/Http/Controllers/WaterCollectionController.php`, `WaterPaymentController.php`
- `app/Http/Middleware/WaterFailureResponse.php`
- `app/Notifications/WaterPaymentUpdated.php`
- `config/water.php`, `routes/water.php`, `storage/app/private/.htaccess` (Apache defense against direct private-file access)
- `resources/views/water/index.blade.php`, `form.blade.php`, `show.blade.php`, `accounts.blade.php`, `queue.blade.php`, `review.blade.php`, `cash.blade.php`, `receipt.blade.php`, `receipt-pdf.blade.php`, `report.blade.php`, `audit.blade.php`, `failure.blade.php`
- `resources/views/water/partials/header.blade.php`, `summary.blade.php`, `payment-form.blade.php`
- `public/css/water.css`, `public/js/water.js`
- `tests/Feature/WaterCollectionTest.php`, `tests/Browser/water-workflow.cjs`
- `tools/water-browser-environment.php`, `water-browser-fixture.php`, `water-browser-router.php`, `water-pdf-check.mjs`
- `docs/water-collections.md`

## Modified files

- `routes/web.php`: loads the module route file.
- `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard/index.blade.php`: scoped water summary queries/cards.
- `resources/views/layouts/dashboard.blade.php`: navigation, notifications link and module CSS/JS.
- `composer.json`, `composer.lock`: PDF dependency and locked dependencies.
- `.env.example`: optional mail flag only; `.env` untouched.
- `.gitignore`: ignores private storage and QA result files.
- `README.md`: links to this setup/implementation guide.

This workspace has no `.git` repository, so Git diff/status could not be used to establish a pre-change baseline. Changes were scoped to the module and its integration points.
