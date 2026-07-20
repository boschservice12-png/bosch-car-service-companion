# Politica de retenție a datelor (P1-06)

Decizie de produs, implementată în `GdprService` + `app:gdpr:purge` (cron
zilnic — vezi `infrastructure/monitoring/monitoring.md`).

## Drepturile clientului în aplicație

| Drept | Cum | Unde |
|---|---|---|
| Portabilitate (export) | JSON descărcabil cu toate datele proprii | Profil → „Descarcă datele mele" (`GET /api/me/export`) |
| Ștergere | Cerere cu re-introducerea parolei; contul se blochează imediat | Profil → „Șterge contul" (`POST /api/me/delete`) |

## Termene

| Categorie | Termen | Ce se întâmplă |
|---|---|---|
| Cont cu ștergere cerută | **30 de zile grație** | contul e blocat; operatorul poate anula cererea (`app:gdpr:cancel-deletion <email>`) dacă clientul se răzgândește |
| După grație | **anonimizare ireversibilă** | email → `sters-…@anonim.local`, nume → „Cont Șters", telefon/adresă/parolă/2FA șterse; conversațiile și mesajele clientului se ȘTERG |
| Vehicule + scadențe + istoric service | **rămân** | evidența operațională a atelierului; legătura de proprietate se închide (`active=false`), deci nu mai indică o persoană identificabilă |
| Jurnal de audit | **365 de zile** | intrările mai vechi se șterg la purjare |
| Notificări în aplicație | **90 de zile** | intrările mai vechi se șterg la purjare |
| Consimțăminte (dovadă GDPR) | păstrate | dovada consimțământului rămâne legată de contul anonimizat |
| Backupuri | **14 zile** | retenția arhivelor (`infrastructure/backup/backup.sh`); datele purjate dispar natural din backupuri după acest interval |

## Garanții tehnice

- contul blocat nu se poate autentifica din momentul cererii (P0-07 —
  `ActiveUserChecker` + invalidarea sesiunii curente);
- un cont anonimizat nu poate fi „revendicat" prin re-înregistrare;
- termenele sunt parametrizabile: `app:gdpr:purge --grace-days=30
  --audit-days=365 --notification-days=90`;
- fiecare pas (cerere, anulare, purjare) lasă urmă în audit.

## Cron recomandat

```cron
# Purjare GDPR zilnică la 03:15 (după backupul de la 02:30)
15 3 * * *  /usr/bin/php /opt/bcsc/backend/bin/console app:gdpr:purge >> /var/log/bcsc-gdpr.log 2>&1
```
