# PWA – instalare simplă pe telefon (customer-web)

Aplicația clientului (`apps/customer-web`, Next.js 15 App Router) se instalează
de pe ecranul principal al telefonului ca aplicație de sine stătătoare —
FĂRĂ aplicație nativă, fără App Store/Google Play, fără push și fără cache
de date.

## Ce s-a modificat

| Fișier | Ce face |
|---|---|
| `public/manifest.webmanifest` | completat: `short_name: Companion`, `scope: /`, iconițe `any` + `maskable` |
| `public/icons/icon-192.png`, `icon-512.png` | iconițele existente (glifă roată dințată pe fondul temei) |
| `public/icons/icon-maskable-512.png` **(nou)** | variantă cu margine de siguranță ~12% — nu se taie pe Android |
| `public/icons/apple-touch-icon.png` **(nou)** | 180×180 pentru iPhone (iOS rotunjește singur colțurile) |
| `app/layout.tsx` | `apple-touch-icon`, `appleWebApp` (`capable`, titlu „Companion"), montarea `PwaSetup` |
| `components/pwa/device-detection.ts` **(nou)** | euristici iOS / Safari / ecran mic |
| `components/pwa/pwa-status.ts` **(nou)** | standalone?, panou închis în ultimele 7 zile (localStorage — doar data închiderii) |
| `components/pwa/install-companion.tsx` **(nou)** | panoul Android (`beforeinstallprompt`), instalare doar la apăsarea butonului |
| `components/pwa/ios-install-guide.tsx` **(nou)** | ghidul în 3 pași pentru iPhone/Safari |
| `components/pwa/pwa-setup.tsx` **(nou)** | punct unic de montare + înregistrarea service worker-ului |
| `public/sw.js` **(nou)** | service worker MINIMAL: doar fallback de navigare către `/offline.html` |
| `public/offline.html` **(nou)** | „Nu există conexiune la internet." + buton „Încearcă din nou" |
| `app/globals.css` | stilul panoului + țintă de atingere: orice `.btn` are minim 44px |
| `backend/config/packages/framework.yaml` | sesiune de 30 de zile (`cookie_lifetime` + `gc_maxlifetime` = 2 592 000s) |

`theme_color` rămâne `#0a2540` — tokenul real al proiectului (`--primary`);
roșul `#E2001A` este doar accent. O bară de sistem roșie peste interfața
navy ar fi arătat străin de aplicație.

## Cum funcționează pe Android (Chrome)

1. Clientul deschide adresa Companion și se autentifică.
2. Chrome emite `beforeinstallprompt` → apare panoul „Instalează Companion
   pe telefon" (doar pe ecran de telefon, doar dacă nu rulează deja
   standalone și nu a fost închis în ultimele 7 zile).
3. Butonul „Instalează" pornește dialogul nativ; refuzul sau ✕ ascunde
   panoul pentru 7 zile (`localStorage: bcsc.pwa.installDismissedAt`).
4. Iconița „Companion" apare pe ecranul principal; pornește standalone
   (fără bara browserului), pe `start_url: /` — autentificat → Acasă,
   neautentificat → redirecționare la login.

## Cum funcționează pe iPhone (Safari)

Safari nu are `beforeinstallprompt`, deci se afișează ghidul manual
(doar pe iOS + Safari, cu aceeași pauză de 7 zile): Partajare →
„Adăugați la ecranul principal" → „Adăugați". Iconița folosește
`apple-touch-icon.png`; aplicația pornește standalone datorită
`appleWebApp.capable`.

## Sesiunea

Cookie-ul de sesiune (PHPSESSID) are acum `Max-Age=2592000` (30 de zile),
rămâne `HttpOnly` + `SameSite=Lax` + `Secure` (auto, activ pe HTTPS), iar
`gc_maxlifetime` pe server e aliniat — deci clientul nu se re-autentifică
la fiecare deschidere. Logout-ul invalidează sesiunea imediat; conturile
dezactivate pierd accesul imediat (P0-07); nimic sensibil nu se ține în
localStorage (doar data închiderii panoului și limba aleasă).

## Offline

Un singur comportament: navigarea fără internet arată `/offline.html`
(mesaj românesc + „Încearcă din nou"). Service worker-ul NU cache-ază
API-uri, date de client/vehicul, scadențe, documente, istoric, oferte sau
mesaje — datele apar doar cu conexiune, mereu proaspete.

## Lăsate deoparte INTENȚIONAT (pași viitori separați)

- web push notifications (necesită serviciu de push + preferințe);
- service worker complet cu cache de rulare;
- funcții offline parțiale;
- împachetare App Store / Google Play (TWA);
- notificări email — nu există încă mailer în backend, vezi
  `EMAIL_NOTIFICATION_TODO.md`.

Pentru push mai târziu: se extinde `public/sw.js` cu handler `push` +
`notificationclick`, se adaugă abonarea (VAPID) într-un endpoint nou și
UI de consimțământ — restul infrastructurii de față rămâne neschimbat.

## Cerințe de mediu

- **HTTPS obligatoriu în producție** — fără el nu există instalare PWA și
  nici service worker (localhost e exceptat pentru dezvoltare).
- Nginx-ul servește `/manifest.webmanifest`, `/sw.js`, `/offline.html` și
  `/icons/*` ca fișiere statice ale aplicației client (deja acoperit de
  proxy-ul existent al Next).

## Cum se testează instalarea

1. Stiva pornită (vezi `e2e/README.md`), pe telefon sau emulator, pe HTTPS
   (sau localhost).
2. Android/Chrome: meniul ⋮ → „Install app" sau panoul din aplicație;
   verificați că iconița pornește fără bară de browser.
3. iPhone/Safari: urmați ghidul afișat; verificați iconița și pornirea.
4. Verificări automate existente: `node mobile-sweep.mjs` (lățimi
   360/390/412/430 — fără overflow, butoane ≥44px) și `node pwa-check.mjs`
   (SW activ, panouri, pauza de 7 zile) din scratchpad-ul sesiunii de
   dezvoltare, plus `npx tsc --noEmit && npx next build`.
