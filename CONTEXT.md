# Fengbro AI Domain Context

## Domain Terms

- **Workspace**: the personal operations system that groups subscriptions, food inventory, notes, media, documents, banks, routines, tools, settings, and notifications.
- **Bank account**: a stored financial account in `bank` whose balance is tracked through the `deposit` field.
- **E-ticket**: a stored non-bank balance account in `bank`, such as transport cards or wallet-like stored value.
- **Bank balance adjustment**: a user action that changes one or more bank account `deposit` values by setting a target number or applying a plus/minus amount.
- **Notification setting**: browser-managed configuration stored in the `settings` table, such as `RESEND_API_KEY`, recipient email, sender email, and VAPID keys.

## Refactor Notes

- Keep page files focused on rendering and orchestration.
- Move reusable domain rules into `includes/*_helpers.php` modules when they are shared or conceptually independent from a page.
- Prefer small refactor commits that keep the app runnable after every step.
