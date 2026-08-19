# PWA — simple phone installation (customer-web)

The customer application (`apps/customer-web`, Next.js 15 App Router) installs to
the phone's home screen as a standalone app — with **no** native app, no App
Store or Google Play, no push, and no data caching.

The customer-facing version of this guide, in Romanian, is
[`GHID_INSTALARE_COMPANION_RO.md`](GHID_INSTALARE_COMPANION_RO.md).

## What was changed

| File | What it does |
|---|---|
| `public/manifest.webmanifest` | Completed: `short_name: Companion`, `scope: /`, `any` + `maskable` icons |
| `public/icons/icon-192.png`, `icon-512.png` | The existing icons (cog glyph on the theme background) |
| `public/icons/icon-maskable-512.png` | Variant with a ~12% safe margin so Android does not crop it |
| `public/icons/apple-touch-icon.png` | 180×180 for iPhone (iOS rounds the corners itself) |
| `app/layout.tsx` | `apple-touch-icon`, `appleWebApp` (`capable`, title "Companion"), mounts `PwaSetup` |
| `components/pwa/device-detection.ts` | iOS / Safari / small-screen heuristics |
| `components/pwa/pwa-status.ts` | Standalone? Panel dismissed in the last 7 days (localStorage — only the dismissal date) |
| `components/pwa/install-companion.tsx` | The Android panel (`beforeinstallprompt`); installation only on a button press |
| `components/pwa/ios-install-guide.tsx` | The three-step guide for iPhone/Safari |
| `components/pwa/pwa-setup.tsx` | Single mount point + service worker registration |
| `public/sw.js` | MINIMAL service worker: navigation fallback to `/offline.html` only |
| `public/offline.html` | "No internet connection." plus a "Try again" button |
| `app/globals.css` | Panel styling + touch targets: every `.btn` is at least 44px |
| `backend/config/packages/framework.yaml` | 30-day session (`cookie_lifetime` + `gc_maxlifetime` = 2,592,000s) |

`theme_color` stays `#0a2540` — the project's real token (`--primary`). The red
`#E2001A` is only an accent; a red system bar over the navy interface would have
looked foreign to the app.

## How it works on Android (Chrome)

1. The customer opens the Companion address and logs in.
2. Chrome fires `beforeinstallprompt` → the "Install Companion on your phone"
   panel appears (phone-sized screens only, only if not already running
   standalone, and only if it has not been dismissed in the last 7 days).
3. The "Install" button starts the native dialog; declining or pressing ✕ hides
   the panel for 7 days (`localStorage: bcsc.pwa.installDismissedAt`).
4. The "Companion" icon appears on the home screen and launches standalone (no
   browser bar) at `start_url: /` — logged in goes to Home, logged out redirects
   to the login page.

## How it works on iPhone (Safari)

Safari has no `beforeinstallprompt`, so a manual guide is shown (iOS + Safari
only, with the same 7-day pause): Share → "Add to Home Screen" → "Add". The icon
uses `apple-touch-icon.png`, and the app starts standalone thanks to
`appleWebApp.capable`.

## The session

The session cookie (PHPSESSID) has `Max-Age=2592000` (30 days) and remains
`HttpOnly` + `SameSite=Lax` + `Secure` (automatic over HTTPS), with the server's
`gc_maxlifetime` aligned — so the customer does not have to log in every time
they open the app. Logout invalidates the session immediately; disabled accounts
lose access immediately (P0-07). Nothing sensitive is kept in localStorage — only
the panel dismissal date and the chosen language.

## Offline

A single behaviour: navigating without a connection shows `/offline.html`. The
service worker does **not** cache APIs, customer or vehicle data, deadlines,
documents, history, quotes or messages — data appears only with a connection and
is always fresh.

## Deliberately left out (separate future steps)

- Web push notifications (needs a push service plus preferences);
- a full service worker with runtime caching;
- partial offline functionality;
- App Store / Google Play packaging (TWA);
- email notifications — there is still no mailer in the backend, see
  [`EMAIL_NOTIFICATION_TODO.md`](EMAIL_NOTIFICATION_TODO.md).

For push later: extend `public/sw.js` with `push` and `notificationclick`
handlers, add VAPID subscription in a new endpoint, and a consent UI. The rest of
the infrastructure here stays unchanged.

## Environment requirements

- **HTTPS is mandatory in production** — without it there is no PWA installation
  and no service worker (localhost is exempt for development).
- The frontend serves `/manifest.webmanifest`, `/sw.js`, `/offline.html` and
  `/icons/*` as static files of the customer application (already covered by the
  existing Next proxy).

## How to test the installation

1. Stack running (see [`../e2e/README.md`](../e2e/README.md)), on a phone or
   emulator, over HTTPS (or localhost).
2. Android/Chrome: ⋮ menu → "Install app", or the in-app panel; check that the
   icon launches without a browser bar.
3. iPhone/Safari: follow the displayed guide; check the icon and the launch.
4. Plus `npx tsc --noEmit && npx next build`.
