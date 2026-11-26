# Payments & Collections navigation

The module now uses icon navigation pills with padded surfaces, emerald active states, hover states, keyboard focus rings, and `aria-current="page"`. The primary row stays on one line and scrolls horizontally when space is limited. Secondary options wrap naturally below it. Neither navigation row is sticky or creates a vertical scroll container. Inline Lucide-style SVGs require no external UI framework or JavaScript.

## Role behavior

| Actual authorization | Main destinations | Related tasks |
| --- | --- | --- |
| Faculty and other users without financial oversight | My Payments, Transaction History, Receipts | Water and Other collection filters; historical records within Transactions |
| Active Treasurer with payments.view and payments.manage | Overview, Collections, Transactions, Reports, Treasurer Wallet, Payment Settings | Create/manage collections; verification queue within Transactions; QR accounts within Settings |
| Configured Administrator / President (`admin`, payments.view) | Financial Overview, Collections, Transactions, Reports, Treasurer Wallet | Audit trail within Reports; financial management controls remain unavailable |

The existing `config/roles.php` identifies `admin` as Administrator / President. An unconfigured legacy `president` role does not gain financial oversight from its name. With the wallet simulation enabled, its existing role-based wallet access remains visible; with simulation disabled, the existing payments.view requirement applies. No role or permission definitions were edited.

## Preserved paths and behavior

All routes remain unchanged. Existing Water and Other category URLs still work. Collection details retain members, edit/manage, verification, cash, waiver, report, and export actions under their existing authorization. Transactions retain the status/method/date/search filters and details/receipt links. Historical records remain accessible as a related Transactions destination. Reports retain per-collection exports. Wallet pages and payment settings retain their existing contents and controls.

The Receipts destination uses `water.demo.index?view=receipts`, filtering the existing read-only payment-history presentation to rows that already have an authorized receipt URL, before pagination. Filters and clear links preserve this view. Receipt generation, ownership checks, and receipt contents are unchanged. Simulation markers and separate accounting remain intact.

Officer Overview shows the existing collection summary, action shortcuts, recent submission activity, and personal outstanding contributions. Recent activity reads existing submissions without changing financial records. Checkout and detail pages retain the same primary navigation so their current section remains visible.

No changes were made for this redesign to Xendit integrations, payment-session logic, webhook authentication, ngrok, database configuration, accounts, roles, permission definitions, authentication, 2FA, APP_KEY, or receipt processing. No migration was run against the installed database. Tests use the guarded in-memory SQLite setup.

## Verification

118 tests passed with 1,376 assertions across PaymentNavigationTest, WaterCollectionTest, PaymentModuleTest, TreasurerWalletTest, XenditDemoTest, XenditGatewayTest, and PayMongoGatewayTest. Navigation tests follow every primary destination and its section links for faculty, treasurer, and Administrator / President, assert the current section, check forbidden management requests, verify old collection bookmarks, and check receipt owner scope and pagination. Legacy-president permissions are tested separately. PHP syntax checks and Blade compilation passed. Compiled views were cleared afterward.

Browser automation inventory returned `The browser access host is unavailable`; no browser preview could initialize. Desktop, tablet, mobile, dark-mode, hover, focus, and scroll behavior still require visual review in a browser. Responsive CSS includes a single scrollable primary row, wrapping secondary actions, 44–48px touch targets, focus outlines, and reduced-motion handling, but these visual outcomes are not claimed as browser-verified.
