# Payments & Collections refinement — 7 November 2026

Implemented locally at http://127.0.0.1:8000. No gateway service, webhook contract, provider configuration, environment, database connection, route identifier, or tunnel configuration was changed by this UI task.

## Experience

- **Complete Payment** is a dedicated, compact checkout. Online Payment is first and selected, with Continue to Payment and a single required simulation acknowledgement. Existing checkout POST routes, CSRF, server-side amounts, reservation/idempotency and provider verification remain in use.
- Manual QR Payment is a native, keyboard-accessible collapsed disclosure. No QR dialog opens on page load or on return from another app. Opening the disclosure reveals the existing account, instructions, reference, amount, date/time, proof-upload and Treasurer-review workflow. Validation errors reopen it. Native disclosure avoids focus traps and overlapping modal scroll containers.
- **Transaction History** at the existing `/dashboard/water/demo` route is a read-only presentation of isolated online attempts, real/manual submissions, cash entries, and unresolved legacy gateway attempts. Completed gateway attempts already represented by a submission are not duplicated. Search covers contribution/reference; filters cover method, state and date, with 20-row pagination and responsive cards. Old archival Payment Records retain their existing route and authorization.
- Faculty see only their own entries; existing `payments.view` oversight sees the club history. Simulation labels remain explicit and no combined money totals are calculated. Treasurer review actions and Administrator restrictions are unchanged.
- Result screens use Payment Completed, Payment Processing, Payment Unsuccessful and Payment Expired. Only persisted confirmed state exposes a receipt. Check status/resume actions use the existing attempt; returns never settle payments.
- Manual and simulation receipts share an emerald/gold FacultyConnect document layout with the existing logo, payer/reference/date metadata, and print styles. Simulation receipts keep `DEMO-` numbers and “Simulation — No Funds Collected.” Real receipt data still comes from its authoritative snapshot.
- The existing receipt renderer now also serves authorized confirmed simulation PDF downloads via the existing receipt URL plus `?download=1`. Dompdf retains disabled remote access, PHP and JavaScript and its existing private chroot/cache. A JPEG copy of the logo avoids this PHP installation's missing GD extension.
- Shared, payment-scoped styles refine contributions, collection management, review screens, filters, buttons and responsive spacing. Gateway/provider names remain in internal identifiers, not ordinary payment headings.

## Verification

- Baseline: **197 tests, 1,875 assertions passed**.
- Final full suite: **199 tests, 1,916 assertions passed**. Logs: `storage/logs/payments-ui-suite.txt` and `.xml`.
- Added coverage: merged history ownership, oversight, method/reference/status/date filters (including end-date-only), pagination, Administrator review denial, online-first/collapsed-manual markup, verified-only PDF access and unchanged real receipt count.
- Existing mocked checkout, callback, replay, capture mismatch, financial isolation, manual submission/review, receipt snapshot, wallet and authentication suites remain passing. Label assertions were updated only for intentional UI changes.
- Static HTML responses from synthetic in-memory tests were rendered in an isolated Chrome profile. Desktop (1440), tablet (820) and exact mobile (390) previews were inspected. Checked checkout/result/history mobile scroll widths equal viewport width. These are render-only fixtures, not authenticated live-browser interaction tests.
- Both synthetic simulation and manual receipt PDFs were rendered using Windows' built-in PDF renderer and visually inspected: one A4 page each, readable metadata, no clipping or blank extra pages. Private evidence: `storage/app/private/ui-preview/`, including `layout-checks.json`, screenshots and PDF page PNGs.
- Home returned HTTP 200. PHP/JavaScript syntax checks passed.
- Financial tables still match the November 7 preservation baseline. The comparison differs only in `users`, `trusted_devices`, and `audit_logs`; their cause was not attributed to this task. Main database identity remains `ccis_local_20261018_164245`, port 3307, recovery mode 0, migrations 14, real receipts 2. Read-only inspection currently finds one confirmed isolated demo attempt.

## Verification limits

The browser/desktop automation bridge still fails with “failed to write kernel assets.” Static rendering provided visual QA, but live keyboard navigation, proof drag/drop, browser print interactions and an authenticated desktop/mobile walkthrough still need manual acceptance. No external checkout or hosted payment was initiated or retested in this redesign. The existing confirmed ledger entry was observed, not independently reverified with the provider. No claim of a new end-to-end payment is made.

History currently aggregates authorized rows in application memory to allow cross-database sorting and searching encrypted references. It is appropriate to this local dataset; a larger deployment should use a bounded read projection without merging financial ledgers.

## Source files

Application/presentation:
- `app/Http/Controllers/XenditDemoController.php` (history and receipt presentation only)
- `app/Services/PaymentHistory.php` (new read-only history)
- `app/Services/WaterReceiptPdf.php`
- `resources/views/layouts/dashboard.blade.php` (scoped stylesheet inclusion)
- `resources/views/water/checkout.blade.php`
- `resources/views/water/index.blade.php`
- `resources/views/water/partials/navigation.blade.php`
- `resources/views/water/partials/receipt-document.blade.php` (new shared document)
- `resources/views/water/demo/option.blade.php`
- `resources/views/water/demo/index.blade.php`
- `resources/views/water/demo/show.blade.php`
- `resources/views/water/demo/receipt.blade.php`
- `resources/views/water/demo/receipt-pdf.blade.php` (new)
- `resources/views/water/receipt.blade.php`
- `resources/views/water/receipt-pdf.blade.php`
- `resources/views/water/review.blade.php`
- `resources/views/water/online-status.blade.php`
- `public/css/payments-refined.css` (new)
- `public/css/payment-receipt.css` (new)
- `public/images/ccis-receipt-logo.jpg` (new derivative of existing logo)
- `public/js/water.js`

Verification:
- `tests/Feature/XenditDemoTest.php`
- `tests/Feature/XenditGatewayTest.php`
- `tests/Feature/PayMongoGatewayTest.php`
- `tests/Feature/WaterCollectionTest.php`
- `tests/Feature/TreasurerWalletTest.php`
- `tools/build-payment-previews.cjs`
- `tools/render-payment-previews.cjs`
- `tools/Render-ReceiptPreview.ps1`
- This report and the integration report's UI handoff note.
