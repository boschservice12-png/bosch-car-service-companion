# ADR 0002 — Authentication and sessions

- **Status:** Accepted (confirmed by the client, 2026-07)
- **Context:** Private-individual customers plus workshop administrators. A PWA.
  Requirements: admin 2FA, login rate limiting, strict data isolation.
- **Decision (v1):**
  - **Customer:** **email + password** as the primary and only method in v1.
    Phone OTP stays prepared behind the `OtpSenderInterface` but is **disabled**
    until an SMS provider exists.
  - **Admin (SERVICE_ADMIN):** email + password + mandatory **TOTP 2FA**.
  - **Session:** `httpOnly`, `Secure`, `SameSite=Lax` cookie, with CSRF
    protection for mutations. No tokens in `localStorage`.
  - **Passwords:** the framework-recommended hashing (Symfony PasswordHasher —
    bcrypt/argon2id).
  - **Rate limiting:** Symfony RateLimiter (Redis) on login, request-code,
    verify-code, messages, and upload.
- **Consequences:** good protection against XSS exfiltration; requires CSRF
  handling in the frontend. OTP stays feature-flagged until a provider contract
  exists.

## Current state (2026-08) — corrected

An earlier revision of this ADR stated that the TOTP enrolment flow and
login verification were "not yet active". **That is no longer true, and had it
remained true it would have been a security problem worth flagging loudly.**

As shipped:

- TOTP enrolment and login verification are implemented and **enforced in
  production**. `admin_2fa_enforce_enrollment` is `true` under `when@prod` in
  `backend/config/services.yaml`.
- `TwoFactorGuardListener` blocks `/api/admin` routes for any admin who has not
  completed enrolment. The first login lands on the enrolment screen.
- Backup codes are issued hashed. `app:2fa:reset` can reset a staff account and
  writes an `identity.2fa_reset` audit entry.
- Anti-replay protection for TOTP codes is in place.
- Disabled accounts are rejected at authentication time by `ActiveUserChecker`
  (P0-07), and the current session is invalidated.

The earlier restriction — "creating real admin accounts in production is blocked
until the 2FA gate ships" — no longer applies.
