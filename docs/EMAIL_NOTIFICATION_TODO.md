# Email notifications — design notes for a future step

**Status: not implemented.** This is the largest gap between the pilot and real
users. See [Roadmap](ROADMAP.md).

Finding (audit 2026-07, still true 2026-08): the backend has no email-sending
infrastructure — `symfony/mailer` is not installed and there is no `MAILER_DSN`.
Notifications exist only as in-application records (`Notification` plus
`SendNotificationHandler`, which writes to the database and logs) and as a manual
WhatsApp/email flow through the 🔔 Notifications page. Email sending was
deliberately **not** built blind into the PWA task.

## What needs building

### 1. Backend service

- `composer require symfony/mailer` plus the chosen transport;
- an `EmailChannel` in the Notification module, called from
  `SendNotificationHandler` when the channel is `email` and the user has a real
  address (not `import-…@clienti.local` or `…@anonim.local`);
- opt-out handling (a profile preference, to be added).

### 2. SMTP transport / provider — a product decision

- options: the workshop's own mailbox SMTP, or a transactional provider
  (Brevo, Mailgun and similar have free tiers);
- `MAILER_DSN` in the environment secrets, **not** in the repository;
- SPF/DKIM on the sending domain, a `noreply@…` address.

### 3. Templates (formal Romanian) for these events

- a new message from the workshop (conversation);
- a quote is ready, and quote state changes;
- an approaching deadline: roadworthiness test, insurance, road tax, roadside
  assistance — reusing the thresholds from `app:deadlines:scan`;
- a roadside assistance request changes state;
- a damage claim changes state.

Note the templates need Hungarian and English versions too — the UI is
trilingual, so a Romanian-only notification is a regression relative to the app.

### 4. Configuration

- `MAILER_DSN`, `MAIL_FROM`, `MAIL_FROM_NAME`;
- deadline thresholds already exist in Settings (`notificationThresholds`).

## What already works

`app:deadlines:scan` correctly computes what should be sent and when, and
`NotificationDelivery` is the contract an implementation plugs into.
`ManualNotificationDelivery` marks `MANUAL_ACTION_REQUIRED` / `SKIPPED` and never
a blind `SENT` — so the queue of what *should* have been sent is accurate and
waiting.
