# Security and GDPR strategy

## Authentication and authorisation
- Passwords hashed with Symfony's PasswordHasher (argon2id/bcrypt).
- **TOTP 2FA mandatory for SERVICE_ADMIN.**
- Sessions use `httpOnly` + `Secure` + `SameSite=Lax` cookies; **CSRF**
  protection on every mutation.
- **Object-level authorisation** (Symfony Voters), not merely hiding things in
  the UI — one customer cannot reach another customer's data. Explicitly tested.

## Transport and headers
- **HTTPS mandatory**; HSTS.
- Restrictive CSP, `X-Content-Type-Options`, `X-Frame-Options` /
  `frame-ancestors`, `Referrer-Policy`.
- **Restrictive CORS** — only the customer and admin application origins.

## Rate limiting (Redis)
- `login`, `request-code`, `verify-code`, `messages`, `upload` — separate limits
  for each.

## Uploads and documents
- **MIME + extension** validation plus a size limit.
- Asynchronous **malware scanning** (ClamAV); only `CLEAN` files are served.
  The scanner is fail-closed: if the daemon is unreachable the upload is
  rejected rather than accepted unscanned.
- **Short-lived signed URLs** for download; private storage with no public access.

## Logging and audit
- Structured logs containing **no** passwords, tokens, OTP codes or sensitive
  content.
- A `traceId` propagated through the API and the logs.
- **Audit records** for: authentication, state transitions, publishing and
  correcting history, export, and deletion. Each entry keeps the actor, the
  timestamp, before/after values, and the reason.

## Backup and continuity
- **Daily backup** of the database and document storage, with an off-box copy.
- **A restore procedure that is actually tested** — automated monthly.
- Alerts for: failed backup, unavailable storage, blocked queues.

See [Backup and restore](../BACKUP_AND_RESTORE.md) and
[Monitoring](../MONITORING.md).

## GDPR — concrete deliverables, not checkboxes
- **Consent records** (`consents`, with the text version).
- A **configurable** privacy notice (`application_settings`).
- **Data export** for the data subject.
- **Rectification requests.**
- **Deletion requests**, with documented exceptions for data that must be
  retained legally (for example tax records).
- **Configurable retention policies** — see
  [the retention policy](data-retention-policy.md).

## Secrets
- No secrets in the repository. Local `.env` files are gitignored; production
  secrets live in `.env.prod` on the server (chmod 600), which is the only copy.
