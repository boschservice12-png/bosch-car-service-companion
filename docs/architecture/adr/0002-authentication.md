# ADR 0002 — Autentificare și sesiuni

- **Status:** **Acceptat** (confirmat de beneficiar — 2026-07)
- **Context:** Clienți persoane fizice + admini de service. PWA. Cerințe: 2FA
  admin, rate limiting la login, izolare strictă a datelor.
- **Decizie (v1):**
  - **Client:** **email + parolă** ca metodă principală și unică în v1
    (confirmat). OTP prin telefon rămâne pregătit în spatele interfeței
    `OtpSenderInterface`, dar **dezactivat** până la un eventual furnizor SMS.
  - **Admin (SERVICE_ADMIN):** email + parolă + **2FA TOTP** obligatoriu.
    **Stare implementare:** câmpurile `totp_secret` / `totp_enabled` există pe
    `User`, dar fluxul de înrolare + verificarea la login **nu sunt încă active**
    în acest sprint. **Consecință de securitate:** până la implementare, un cont
    de admin se autentifică doar cu parolă — de aceea crearea de conturi admin
    reale în producție este blocată până la livrarea gate-ului 2FA (task dedicat,
    programat înainte de producție).
  - **Sesiune:** cookie `httpOnly`, `Secure`, `SameSite=Lax`, cu protecție CSRF
    pentru mutații (I14). Fără token-uri în `localStorage`.
  - **Parole:** hashing recomandat de framework (Symfony PasswordHasher — bcrypt/argon2id).
  - **Rate limiting:** Symfony RateLimiter (Redis) pe login, request-code,
    verify-code, mesaje, upload.
- **Consecințe:** protecție bună împotriva XSS-exfiltration; necesită gestiunea
  CSRF în frontend. OTP-ul rămâne feature-flag până la contract furnizor.
