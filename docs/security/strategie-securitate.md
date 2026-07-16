# Strategie de securitate & GDPR

## Autentificare & autorizare
- Parole hash-uite (Symfony PasswordHasher: argon2id/bcrypt).
- **2FA TOTP obligatoriu pentru SERVICE_ADMIN.**
- Sesiuni cu cookie `httpOnly`+`Secure`+`SameSite=Lax`; protecție **CSRF** la mutații.
- **Autorizare la nivel de obiect** (Symfony Voters), nu doar ascundere în UI —
  un client nu poate accesa datele altui client. Testat explicit (DoD).

## Transport & headers
- **HTTPS obligatoriu**; HSTS.
- CSP restrictivă, `X-Content-Type-Options`, `X-Frame-Options`/`frame-ancestors`,
  `Referrer-Policy`.
- **CORS restrictiv** — doar originile aplicațiilor client/admin.

## Rate limiting (Redis)
- `login`, `request-code`, `verify-code`, `messages`, `upload` — limite separate.

## Upload & documente
- Validare **MIME + extensie** + limită de dimensiune.
- **Scanare malware** (ClamAV) asincronă; se servesc doar fișiere `CLEAN`.
- **URL-uri temporare semnate** pentru descărcare; storage privat (fără acces public).

## Logare & audit
- Loguri structurate **fără** parole, token-uri, coduri OTP sau conținut sensibil.
- `traceId` propagat în API și loguri.
- **Audit** pentru: autentificare, tranziții de stare, publicare/corecție istoric,
  export, ștergere. Auditul păstrează actor, dată, before/after, motiv.

## Backup & continuitate
- **Backup zilnic** al bazei și storage.
- **Procedură de restaurare testată** periodic.
- Alerte pentru: backup eșuat, storage indisponibil, cozi blocate.

## GDPR (livrabile concrete, nu doar checkbox)
- **Evidența consimțămintelor** (`consents`, versiune text).
- Informare de confidențialitate **configurabilă** (`application_settings`).
- **Export de date** al persoanei vizate.
- **Cerere de rectificare.**
- **Cerere de ștergere**, cu excepții documentate pentru date ce trebuie păstrate
  legal (ex. evidențe fiscale) — vezi întrebarea blocantă #6.
- **Politici de retenție configurabile.**

## Secrete
- Fără secrete în repo. `.env` local ignorat; producție prin secret manager.
