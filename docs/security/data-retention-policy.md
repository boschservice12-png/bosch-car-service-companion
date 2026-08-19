# Data retention policy (P1-06)

A product decision, implemented in `GdprService` + `app:gdpr:purge`, which runs
daily from the `scheduler` service — see [Operations §2](../OPERATIONS.md).

## Customer rights in the application

| Right | How | Where |
|---|---|---|
| Portability (export) | Downloadable JSON containing all of their own data | Profile → "Download my data" (`GET /api/me/export`) |
| Erasure | Request requiring password re-entry; the account is blocked immediately | Profile → "Delete my account" (`POST /api/me/delete`) |

## Retention periods

| Category | Period | What happens |
|---|---|---|
| Account with deletion requested | **30-day grace period** | The account is blocked; an operator can cancel the request (`app:gdpr:cancel-deletion <email>`) if the customer changes their mind |
| After the grace period | **irreversible anonymisation** | email → `deleted-…@anonim.local`, name → "Deleted account", phone/address/password/2FA erased. Fully DELETED: conversations and messages, quote/roadside/mobility requests, damage claims, taxes, notifications, and documents uploaded by the customer (including the files in storage) |
| Vehicles + deadlines + service history | **retained** | The workshop's operational record; the ownership link is closed (`active=false`) so it no longer points at an identifiable person |
| Audit log | **365 days** | Older entries are removed at purge time |
| In-app notifications | **90 days** | Older entries are removed at purge time |
| Consents (GDPR evidence) | retained | Proof of consent stays attached to the anonymised account |
| Backups | **14 days** local, **30 days** off-box | Purged data disappears from backups naturally after this interval |

## Technical guarantees

- A blocked account cannot authenticate from the moment of the request (P0-07 —
  `ActiveUserChecker` plus invalidation of the current session).
- An anonymised account cannot be "reclaimed" by re-registering.
- The periods are parameterised:
  `app:gdpr:purge --grace-days=30 --audit-days=365 --notification-days=90`.
- Every step — request, cancellation, purge — leaves an audit trail.

## Scheduling

The purge runs automatically in the `scheduler` service at `GDPR_PURGE_HOUR`
(default 04:00 UTC), deliberately **after** the 03:00 backup: save first, then
delete irreversibly.

Do not add a host cron for this — it would run the purge twice.
