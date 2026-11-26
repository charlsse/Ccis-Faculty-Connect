# FacultyConnect navigation, notifications and Content Manager update

Implemented September 28, 2026. Both pages of `website edits.pdf` were rendered and visually inspected before editing. The pasted request supplied the implementation requirements.

## Navigation and account access

- Removed **My Profile, Security Settings, Logout, and Logout & Forget This Device** from the shared desktop/mobile sidebar only.
- Retained existing workspace routes and role checks. Directory remains available to admin/secretary; User Accounts and Content Manager remain admin-only.
- The topbar account menu retains profile, security, and both distinct POST/CSRF-protected logout actions. Name and role appear above the account links; dividers separate sign-out actions. Forget-device includes an explanation and gold accent.
- Added an accessible account trigger label/control relationship, focus restoration with Escape, outside-click/focus dismissal, and viewport height limits. Mobile keeps the avatar trigger.
- Authentication, onboarding, invitations, session handling, TOTP, device trust, payment verification and authorization routes were retained. No database connection settings, credentials, or `.env` values were changed.

## Notification audit: before

- `WaterNotice` / `water_notices` already stored local in-app water updates and read timestamps.
- Publishing a collection already notified assigned members. Payment submission/review, cash recording and waivers also used that service. Water pages filtered notices through collection visibility.
- `WaterPaymentUpdated` supplied optional mail after commit, with delivery-failure handling that preserved the local notice.
- The topbar bell only linked to the water page and displayed an unread dot. There was no unified dropdown/history/read-all.
- Account invitations, verification and password recovery had their existing mail flows. Audit logging already captured password changes, failed sign-ins, trusted-device operations and 2FA changes.
- Laravel Notifiable, queues and jobs tables were present; no generic database notifications table, portal broadcast/WebSocket implementation, announcement notification delivery or security alert delivery existed.
- Announcement audience was unrestricted descriptive text, not an enforced audience rule. Ordinary users were filtered by publication and expiry only.

## Notification implementation

- Added Laravel's standard `notifications` table and `PortalNotification` database channel. Security alerts use the native notification API. Announcement records use the same native schema/relation with a stable per-announcement/per-user UUID and atomic insert-ignore to avoid duplicates on concurrent refreshes.
- Kept existing water records and read states as the water source, rather than copying or resetting them. The unified service combines the two sources.
- A bell badge caps its visible count at **9+**, with the full count in its accessible label. The dropdown shows five recent visible notices with collection/announcement/security icons, timestamps, explicit Read/Unread text, authorized actions and read controls.
- Added a paginated history page with All, Unread, Collections, Announcements and Security filters. Existing water payment updates are included under Collections.
- Individual/read-all actions are POST requests with CSRF protection. Every lookup is scoped to the current user and current content visibility. Redirects are generated from fixed application routes, never notification-supplied external URLs.
- Expired, unpublished, deleted, newly restricted announcements and inaccessible water collections disappear from the feed and unread count. Opening their stale notification action is rejected.

### Events and recipients

**Collections:** the existing publish transition still sends only to assigned members. Messages now include the real title, period, assigned amount and due date. Draft creation does not notify. Reopening/republishing continues the existing availability-update behavior. Existing payment notices and optional email remain intact.

**Announcements:** currently published, unexpired announcements are synchronized to eligible active users on save. Refresh/navigation catches future-scheduled publications once their date arrives, without requiring a queue worker. Notifications are not backfilled for publications preceding the account's creation. Refreshes and edits do not duplicate the original notice.

Audience choices are now explicit: **All Faculty, All Members, Faculty, Administrators, Secretaries, Treasurers, Staff**. The existing “All Faculty” default retains its portal-wide meaning, including officers and legacy staff; “All Members” is an equivalent explicit label. The other choices match a single role. This is explained beside the selector. Non-editor index/detail/dashboard access follows these rules, not just notifications. Authorized announcement editors can still manage all announcements. Unknown legacy labels are not broadcast or exposed to ordinary viewers; an editor must explicitly select a defined audience before saving them. No existing audience data was rewritten.

**Security:** a notice is created on the fifth failed password attempt or fifth failed 2FA challenge, matching the existing protection threshold. A single typo produces no alert. Repeated alerts of the same kind are limited to one per account per hour. Existing rate-limit thresholds and responses remain unchanged. Successful password changes/resets, device revocation, other-device revocation, administrator session/trust revocation, administrator 2FA reset and recovery-code regeneration also produce fixed, neutral messages through the existing audit event points.

Notifications never copy passwords, codes, secrets, reset tokens, device tokens, IPs or user-agent details into their messages. There is no fabricated attacker, location or new-device detection.

### Channels and dependencies

All new delivery is local database in-app delivery. Existing optional water email is unchanged. No new package, API, plugin, push provider, polling timer, WebSocket server, or external account was added. Notifications become visible after navigation/refresh; this is not realtime push. Security/announcement delivery does not depend on email.

## Content Manager

Created seven categories reflecting the actual controls:

1. Branding — existing website icon upload, preview and restore.
2. Hero slides — existing slide copy, visual selection and images.
3. Portal preview — existing metrics and progress bars.
4. About — copy and badges.
5. Features — module preview cards.
6. Benefits — existing benefit cards.
7. Call to action — existing CTA fields.

Desktop uses keyboard-operable tabs with Arrow keys/Home/End. Phones use one readable category select. Only the active editing panel is shown. All panels remain in the DOM, retaining unsaved text and selected files across category switches. Session storage remembers the selected category, not sensitive form data. The icon remains its own save operation; **Save Content** continues to save the existing landing form. Category switching does not submit or reload either form.

Client validation reveals and focuses the first invalid field before displaying the browser error. Server validation selects the relevant category, marks its fields invalid and lists the actual errors. With JavaScript unavailable, the original stacked forms remain available. Text/file preservation applies to switching categories, not reloading/leaving the page; browsers require file reselection after a failed server submission.

## Migration and files

Applied the additive `2026_09_28_000000_create_notifications_table.php` migration to the configured local application database and the isolated QA SQLite database. It creates only the new table. Existing records/tables were not deleted or rebuilt.

Application changes:

- `app/Services/PortalNotifications.php` — unified feed, announcement synchronization, safe security delivery, read controls.
- `app/Notifications/PortalNotification.php` — native database notification.
- `app/Http/Controllers/NotificationController.php` — history and owned read actions.
- `app/Services/AuditLogger.php`, `app/Http/Controllers/AuthController.php` — meaningful security event hooks.
- `app/Models/Announcement.php`, `app/Http/Controllers/AnnouncementController.php`, `app/Http/Controllers/DashboardController.php`, `app/Providers/AppServiceProvider.php` — audience enforcement and publication synchronization.
- `app/Http/Controllers/WaterCollectionController.php` — collection message details.
- `routes/web.php` — authenticated notification routes.
- `resources/views/layouts/dashboard.blade.php` — sidebar/account menu cleanup and bell integration.
- `resources/views/dashboard/partials/notification-menu.blade.php`, `notification-item.blade.php`, `resources/views/dashboard/notifications.blade.php` — notification UI.
- `resources/views/dashboard/announcements/index.blade.php`, `show.blade.php` — explicit audience controls.
- `resources/views/dashboard/content-manager.blade.php` — category panels and validation summary.
- `public/js/workspace-updates.js`, `public/css/workspace-updates.css` — interactions, accessibility and both-theme responsive styling.
- `database/migrations/2026_09_28_000000_create_notifications_table.php` — additive schema.

Verification additions/maintenance:

- `tests/Feature/PortalNotificationsTest.php`.
- `tests/Browser/workspace-updates.cjs`.
- `tools/notifications-browser-fixture.php`.
- `tools/targeted-auth-fixture.php`, `tools/responsive-fixture.php`, `tools/appearance-browser-fixture.php` — collision-resistant synthetic IDs and reuse of a synthetic author. A random duplicate ID interrupted one fixture preparation; it was corrected and the audit rerun successfully.

## Verification and limits

- Current complete PHP suite: **104 tests, 886 assertions, all passed**.
- Existing broad browser audit: **434 light-theme and 434 dark-theme layout checks**, no recorded page/navigation errors.
- Existing appearance regression: **312 layout checks**, no recorded errors; includes theme persistence, reduced motion, carousel behavior, 0/1/3 announcements, favicon upload/error/restore and dark application pages.
- New workspace browser suite: **222 layout checks, no recorded errors**, covering dashboard, both dropdowns, notification history and every CMS category in both themes at **320, 360, 375, 390, 430, 768, 1024, 1280, 1440 and 1920px**. It additionally checks short landscape panels at 768×375. Combined final browser total: **1,402 layout checks**.
- The broad audit additionally covers 412, 1366, 1536 and 1600px; appearance adds 393 and 480px.
- PHP tests verify all five roles' navigation, assigned/unassigned collection recipients, authorized links, publication schedule/expiry/audience changes, unknown legacy labels, owned read actions, capped badges, password/2FA thresholds, alert suppression and safe messages. Existing tests still verify both distinct logout/trust behaviors and the other protected workflows.
- New browser interactions verify Escape/focus behavior, mobile account access/logout, all seven categories, Home/End, unsaved text/file retention, client/server validation reveal, landing save, hero upload/removal, filters and read controls. Tests use isolated synthetic accounts/data, not actual faculty accounts or payments.
- Blade compilation, route registration, PHP syntax and JavaScript syntax checks passed.
- Screenshots are stored in `storage/app/private/workspace-updates-qa/`; existing audit results are in `responsive-qa`, `responsive-qa-dark` and `appearance-qa` below the same private directory.
- Visually inspected desktop/mobile light/dark screenshots of the account dropdown, notification panel/history, dashboard and CMS category views. Retook initial dropdown/tab screenshots after their transitions settled; the final captures have opaque, readable panels and clear active categories.

No live email delivery, production deployment, external push service or physical iOS/Android device was tested. Browser testing uses local desktop Chrome with emulated viewport sizes. The current history implementation merges eligible records in memory before paginating; database-level unified pagination and asynchronous fan-out would be reasonable follow-ups if notification volume becomes large. Scheduled publications depend on navigation/refresh, as intentionally chosen for this local build. Administrators should review any old free-text audience labels and choose the intended explicit audience.
