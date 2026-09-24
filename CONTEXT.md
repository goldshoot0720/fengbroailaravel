# Fengbro AI Domain Context

## Domain Terms

- **Workspace**: the personal operations system that groups subscriptions, trial/first-purchase accounts, reinstall software lists, food inventory, notes, media, documents, banks, routines, tools, settings, and notifications.
- **Trial / first purchase**: one `trialpurchase` row is a service × account pair, grouped by service name, with trial/purchase status and an event date.
- **Reinstall software**: one `reinstall` row is a Windows or Mac package, with optional paid serial hidden behind a view password.
- **Bank account**: a stored financial account in `bank` whose balance is tracked through the `deposit` field.
- **E-ticket**: a stored non-bank balance account in `bank`, such as transport cards or wallet-like stored value.
- **Point**: a loyalty/reward point balance in `bank` (e.g. LINE Pay Point), shown in its own block on the bank page; `deposit` holds the point count (not TWD) and `note` holds remarks such as the expiry date. Rows are classified by `category` (`bank` / `ticket` / `point`) or, when empty, by name keywords (`bankItemCategory()` in `includes/bank_helpers.php`).
- **Bank balance adjustment**: a user action that changes one or more bank account `deposit` values by setting a target number or applying a plus/minus amount.
- **Notification setting**: browser-managed configuration stored in the `settings` table, such as `RESEND_API_KEY`, recipient email, sender email, and VAPID keys.
- **Notification channels**: browser banner + Notification API (`assets/js/notifications.js`), Web Push (`push_send.php` / `push_subscribe.php`), and Resend email (`includes/resend_notifications.php`). Due-date domain rules live in `includes/notification_helpers.php`.

## Refactor Notes

- Keep page files focused on rendering and orchestration.
- Move reusable domain rules into `includes/*_helpers.php` modules when they are shared or conceptually independent from a page.
- Prefer small refactor commits that keep the app runnable after every step.
- Notification due-date queries and payload formatting should go through `notification_helpers.php` so footer, dashboard, push, and resend stay aligned.
- Use `notif_diag.php` / `notifRunSelfCheck()` for read-only notification self-diagnostics (settings page UI).
- Schema checks (`CREATE TABLE IF NOT EXISTS`, missing columns, soft-delete `deleted_at`, performance indexes) go through `includes/schema_cache.php`: once per request plus a 6-hour marker file, so normal requests run zero DDL. Call `fengbroSchemaForget()` after rebuilding tables; `api.php` self-heals by forgetting and retrying when a query hits a missing table/column.
- Site-wide UX helpers live in `assets/js/ux-boost.js` (loaded in the header): request progress bar, double-submit guard, scroll restore after `location.reload()`, success toast after writes, and non-blocking `alert()` toasts via `window.fengbroToast`.
- Optimistic UI lives in `assets/js/optimistic-ui.js` (loaded in the header after `ux-boost.js`). After a write, call `fengbroReload()` instead of `location.reload()`: on the pages listed in `SOFT_PAGES` it fetches the same page and applies only the server-side changes to `#mainContent` (three-way merge against the snapshot `includes/footer.php` takes via `fengbroSnapshotMain()`), keeping JS state such as filters, view modes, open groups and event listeners. It falls back to a full reload when a `<script>` inside the main content changed (embedded data), the main content is missing, or anything fails. Modals, inline add rows/cards, `*Form` panels and `.inline-edit` / `.inline-view` are reset to the server state; mark other transient UI with `data-soft-reset`.
- Deletes go through `deleteInlineItem()` (or `fengbroOptimistic.hide/commit/restore`): the item disappears immediately and comes back if the request fails. Tables with a trash (`article`, `subscription`) offer 復原 in the toast. Pass `fengbroQuiet: true` in the fetch init when the caller shows its own result toast.
- Page responses carry an ETag (`includes/page_cache.php`): data version (bumped at the end of every write request by `fengbroTrackDataWrites()`), today's date, code mtimes, CSRF token and URL. Unchanged pages answer 304 without touching the database. `tools` and `settings` are not cached.
