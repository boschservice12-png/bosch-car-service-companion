# ADR 0002 — Autentificare și sesiuni

- **Status:** **Acceptat** (confirmat de beneficiar — 2026-07)
- **Context:** Clienți persoane fizice + admini de service. PWA. Cerințe: 2FA
  admin, rate limiting la login, izolare strictă a datelor.
- **Decizie (v1):**
  - **Client:** **email + parolă** ca metodă principală și unică în v1
    (confirmat). OTP prin telefon rămâne pregătit în spatele interfeței
    `OtpSenderInterface`, dar **dezactivat** până la un eventual furnizor SMS.
  - **Admin (SERVICE_ADMIN):** email + parolă + **2FA TOTP** obligatoriu.
    **Stare implementare: IMPLEMENTAT.** TOTP prin `spomky-labs/otphp`.
    - Înrolare: `POST /api/2fa/setup` (generează secret + `provisioningUri` pentru
      QR) → `POST /api/2fa/confirm` (cod valid → activează).
    - Login: pentru adminii cu 2FA activat, `POST /api/auth/login` cere `totpCode`;
      lipsă/greșit → 401 `totp_required` (verificat în `TwoFactorLoginListener` pe
      `CheckPassportEvent`, după validarea parolei).
    - Enforcement: `AdminTwoFactorEnforcementListener` blochează rutele
      `/api/admin/*` pentru adminii fără 2FA activat (403), forțând înrolarea.
      Astfel nu există conturi privilegiate protejate doar cu parolă în operare.
  - **Sesiune:** cookie `httpOnly`, `Secure`, `SameSite=Lax`, cu protecție CSRF
    pentru mutații (I14). Fără token-uri în `localStorage`.
  - **Parole:** hashing recomandat de framework (Symfony PasswordHasher — bcrypt/argon2id).
  - **Rate limiting:** Symfony RateLimiter (Redis) pe login, request-code,
    verify-code, mesaje, upload.
- **Consecințe:** protecție bună împotriva XSS-exfiltration; necesită gestiunea
  CSRF în frontend. OTP-ul rămâne feature-flag până la contract furnizor.
