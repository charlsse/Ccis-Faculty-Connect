# Mobile payment sheet and header correction

September 28, 2026. Targeted presentation changes only. No payment service, controller, policy, database schema, QR payload, receiving account, authentication or 2FA changes. PayMongo was not enabled or reintroduced.

## Findings and implementation

| Requested report item | Finding / result |
| --- | --- |
| 1. Giant Close button | The close control used `.button`, inheriting `.water-checkout .button { width:100% }` at the 600px breakpoint. It now uses a component-specific 44px icon control with `aria-label="Close payment"`. |
| 2. Overflow / clipping | The complete dialog was the scroll region; the close/header scrolled away and the underlying page had no payment scroll lock. The shell now has a dynamic viewport maximum height, a stationary header and one independently scrollable body. |
| 3. Excessive spacing | Generic headings, paragraphs, mobile definition-list spacing and a separate QR action inherited unrelated checkout spacing. Scoped spacing, grouped account rows and a QR-associated Save action replace those combinations. |
| 4. Hamburger | Existing CSS already declared a 44px target; no verified 55–65px size was found. Its three grid rows stretched the icon vertically, and surrounding controls had inconsistent chrome. The lines now form a compact 22px-wide flex group inside a 44px target. The off-canvas CSS boundary was 880px while JS used 980px; these now agree at 980px. |
| 5. Production files | `resources/views/water/checkout.blade.php`, `resources/views/layouts/dashboard.blade.php`, `public/css/payment-sheet.css`, `public/css/dashboard.css`, `public/js/water.js`, `public/js/dashboard.js`. |
| 6. CSS / breakpoints | New scoped payment-sheet CSS uses 600px content padding, 340px narrow-phone margins, and the existing 980px mobile navigation boundary. Desktop remains centered at a maximum 560px width. Shell uses vh fallback/dvh and safe-area limits; no fixed content height or scale transform. |
| 7. JavaScript | Keeps QR, help, proof and pending content in the native dialog; preserves proof fields on Back and close/reopen; traps Tab within the sheet; restores opener focus; shares an owner-based scroll lock with the sidebar. Queued close events cannot unlock a reopened sheet. |
| 8. Modal structure | Compact header plus scrolling body. Proof/help markup is moved into the sheet on initialization, preserving server-side forms and their no-JavaScript fallback. Pending status uses the same sheet. No backdrop-click dismissal, so clicking outside cannot discard form input. |
| 9. Payment logic | Unchanged. Amount, account, QR, configured instructions, submission endpoints, validation, status transitions, verification and receipts still use the existing implementation. |
| 10. Desktop | Checked at 1024×768, 1280×800, 1440×900 and 1920×1080. Centered sheet, bounded scroll, normal desktop sidebar. |
| 11. Mobile / tablet matrix | 320×568, 360×640, 375×667, 390×844, 393×852, 412×915, 430×932 and 768×1024. Added 900×900 and 980×800 to check the corrected navigation boundary. |
| 12–13. Light / dark | Both themes checked. Native dialog top layer stays above header, sidebar and menus. Theme-aware header/content/close control; QR retains its white surface without filters or inversion. |
| 14. Landscape | 667×375 and 844×390 checked. Header remains reachable while content scrolls. A sticky action footer was deliberately avoided because it would consume too much of the short viewport; actions remain reachable in the single scroll region. |
| 15. Scroll lock | Payment/sidebar hold separate lock ownership. Original inline body styles and page position are restored on final close. Sidebar focus restoration uses preventScroll. Closed mobile sidebar is inert and desktop navigation regains interaction at its breakpoint. |
| 16. QR | Original configured image copied byte-for-byte into a private QA fixture, with matching SHA-256 hashes. The app still serves the original dynamic QR URL. Image is contained, not cropped or regenerated, on a white scanning surface, at up to 250px. Save QR download exercised. No real payment was made. |
| 17. Step 1 | Correct collection sheet, compact header, prominent dynamic amount, configured receiving account/instructions, QR download and I Have Paid. Long display strings and ₱100/₱500/₱1,000/₱12,500 exercised without horizontal overflow. |
| 18. Step 2 | Remains inside the sheet. Reference, full-width inputs, long filename wrapping, image preview, Back and Submit remain available through internal scrolling. Back preserves input and selected file. Server validation errors reopen this step inside the sheet. |
| 19. Pending | Pending Treasurer Verification opens in the same styled component after submission; closing and View payment status allow reopening. No oversized success graphic. |
| 20. Sidebar | Compact controls, existing backdrop/slide behavior, keyboard loop, Escape, backdrop and navigation dismissal. Shared scroll ownership avoids unlocking an active payment dialog. Reduced-motion preferences disable the slide transition. |
| 21. Automated checks | Backend suite: 135 tests, 1,199 assertions passed. Browser matrix: 251 layout/state checks, zero browser errors. Supplemental Water/Other proof, Treasurer review, receipt, Admin read-only, shared confirmation and sidebar dismissal checks passed with zero browser errors. |
| 22. Limits | Desktop Chromium viewport emulation and reduced-height keyboard simulation were used, not a physical iOS/Android software keyboard. Actual banking-app scanning/settlement and device-specific browser chrome behavior remain unverified. |

## QA artifacts

- `tests/Browser/mobile-payment-sheet.cjs`: both themes, full viewport matrix, header/sidebar, QR top/middle/bottom, focused proof form, preview, reduced viewport height, close/reopen/focus containment, download, server validation and pending.
- `tests/Browser/mobile-payment-regression.cjs`: Water and Other submission/review/receipt, Admin read-only controls, shared confirmation dialogs and sidebar dismissal.
- Results and actual browser screenshots: `storage/app/private/treasurer-qr-qa/mobile-*`.
- Backend output: `storage/logs/mobile-ui-tests.txt`.
- Browser output: `storage/logs/mobile-sheet-browser.txt` and `storage/logs/mobile-sheet-regression.txt`.

The browser fixture has its own compiled-view directory to avoid Windows file-renaming collisions with the backend test runner. It uses synthetic accounts and records, disabled email and disabled gateway configuration; it is a test tool, not an application prerequisite. The production QR/account database record was not changed. No new dependency was installed.
