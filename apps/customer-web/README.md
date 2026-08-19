# customer-web — the customer application (PWA)

Next.js 15 (App Router) + strict TypeScript, mobile-first, WCAG 2.1 AA.
Trilingual: Romanian (default) · Hungarian · English.

Runs on port 3000 in development; served at https://app.bcss.ro in production.

## Customer navigation

Home · Messages · Roadworthiness test (ITP) · Insurance (RCA) · Road tax ·
Roadside assistance validity · Service history · Request a quote · Request
roadside assistance · Request mobility · Damage claim · Taxes and duties ·
Profile.

Route and label names are Romanian in the source — Romanian is the key language
for the i18n dictionaries. See [Architecture §9](../../docs/ARCHITECTURE.md)
before renaming anything user-visible.

## UX rules

- One primary CTA per screen; plain language, no jargon.
- Status = text + icon + colour (colour is never the only indicator).
- Multi-step forms; drafts saved for complex requests.
- Confirmation before irreversible actions.
- Every state handled: loading / empty / success / error / offline / forbidden.
- Verified at 360px, tablet, and desktop.

## API access

The browser never calls the backend directly. Client-side calls go to `/api/*`
on the same origin, and the Next.js server rewrites them onward
(`NEXT_PUBLIC_API_BASE`, `next.config.mjs`). That keeps cookies same-origin and
removes the need for CORS.

## Local development

```bash
npm install && npm run dev        # http://localhost:3000
npx tsc --noEmit && npx next build
```

Needs the backend running — see the [root README](../../README.md).

## PWA installation

Developer notes: [`docs/PWA_SIMPLE_INSTALLATION.md`](../../docs/PWA_SIMPLE_INSTALLATION.md).
Customer-facing guide (Romanian):
[`docs/GHID_INSTALARE_COMPANION_RO.md`](../../docs/GHID_INSTALARE_COMPANION_RO.md).
