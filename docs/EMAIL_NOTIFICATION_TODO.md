# EMAIL_NOTIFICATION_TODO — notificări prin email (pas viitor)

Constatare (audit 2026-07): backend-ul NU are încă infrastructură de
trimitere de email — `symfony/mailer` nu e instalat, nu există
`MAILER_DSN`. Notificările există doar ca înregistrări în aplicație
(`Notification` + `SendNotificationHandler` scrie în DB și loghează) și ca
flux manual WhatsApp/email prin pagina 🔔 Notificări. De aceea NU s-a
construit „orb" trimiterea de email în sarcina PWA.

## Ce trebuie construit

1. **Serviciu backend**
   - `composer require symfony/mailer` + transportul ales;
   - un `EmailChannel` în modulul Notification, apelat din
     `SendNotificationHandler` când canalul e `email` și utilizatorul are
     adresă reală (nu `import-…@clienti.local` / `…@anonim.local`);
   - respectarea opt-out-ului (preferință pe profil — de adăugat).

2. **Transport SMTP / furnizor** (decizie de produs)
   - variante: SMTP-ul căsuței service-ului, sau un furnizor tranzacțional
     (ex. Brevo/Mailgun — au trepte gratuite);
   - `MAILER_DSN` în secretele mediului, NU în repo;
   - SPF/DKIM pe domeniul expeditor, adresă `noreply@…`.

3. **Șabloane (RO oficial) pentru evenimentele:**
   - mesaj nou de la service (conversație);
   - ofertă pregătită + schimbare de stare a ofertei;
   - scadență apropiată: ITP, RCA, rovinietă, asistență rutieră
     (refolosind pragurile din `app:deadlines:scan`);
   - schimbare de stare: cerere de asistență rutieră;
   - schimbare de stare: dosar de daună.

4. **Configurare**
   - `MAILER_DSN`, `MAIL_FROM`, `MAIL_FROM_NAME`;
   - praguri de scadență deja existente în Settings
     (`notificationThresholds`).

5. **Teste**
   - unitare pe construirea șabloanelor (subiect/corp per eveniment);
   - funcționale cu transportul `null://` + `assertEmailCount`;
   - idempotență: refolosirea `deadline_notifications` (prag+canal) ca să
     nu se trimită de două ori același email;
   - verificarea că adresele interne/anonimizate nu primesc email.
