# Fengbro AI Domain Context

## Domain Terms

- **Workspace**: the personal operations system that groups subscriptions, food inventory, notes, media, documents, banks, routines, tools, settings, and notifications.
- **Bank account**: a stored financial account in `bank` whose balance is tracked through the `deposit` field.
- **E-ticket**: a stored non-bank balance account in `bank`, such as transport cards or wallet-like stored value.
- **Bank balance adjustment**: a user action that changes one or more bank account `deposit` values by setting a target number or applying a plus/minus amount.
- **Notification setting**: browser-managed configuration stored in the `settings` table, such as `RESEND_API_KEY`, recipient email, sender email, and VAPID keys.
- **Notification channels**: browser banner + Notification API (`assets/js/notifications.js`), Web Push (`push_send.php` / `push_subscribe.php`), and Resend email (`includes/resend_notifications.php`). Due-date domain rules live in `includes/notification_helpers.php`.

## Refactor Notes

- Keep page files focused on rendering and orchestration.
- Move reusable domain rules into `includes/*_helpers.php` modules when they are shared or conceptually independent from a page.
- Prefer small refactor commits that keep the app runnable after every step.
- Notification due-date queries and payload formatting should go through `notification_helpers.php` so footer, dashboard, push, and resend stay aligned.
- Use `notif_diag.php` / `notifRunSelfCheck()` for read-only notification self-diagnostics (settings page UI).
