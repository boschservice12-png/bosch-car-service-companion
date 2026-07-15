# ADR 0004 — Versiuni de stack

- **Status:** Provizoriu — **de finalizat la pornirea implementării (Etapa 2)**
- **Context:** Promptul cere explicit: „Nu fixa versiuni neverificate. La pornirea
  proiectului, verifică versiunile stabile și suportate și documentează alegerea.”
- **Decizie provizorie (de confirmat înainte de a fixa în `composer.json`/`package.json`):**

  | Componentă | Linie țintă | Notă |
  |---|---|---|
  | PHP | linia stabilă cu suport activ | `declare(strict_types=1)` peste tot |
  | Symfony | ultima versiune LTS suportată | verificat pe symfony.com/releases |
  | Doctrine ORM | linia compatibilă cu Symfony ales | migrations activate |
  | PostgreSQL | ultima versiune stabilă suportată | `timestamptz`, `numeric`, `jsonb` |
  | Redis | linia stabilă | cozi + rate limiter + cache |
  | Node.js | linia LTS activă (≥20) | pentru Next.js |
  | Next.js | 15.x (App Router) | **ales și verificat** — build+typecheck OK |
  | React | 19.x | **ales** împreună cu Next 15 |
  | TypeScript | 5.7.x (strict) | `noUncheckedIndexedAccess` activ |

- **Frontend confirmat:** `apps/customer-web` rulează pe Next.js 15 + React 19 +
  TypeScript strict; `next build`, `next lint` și `tsc --noEmit` trec fără erori.
- **Acțiune rămasă:** la kickoff-ul backend-ului se fixează liniile PHP/Symfony/
  PostgreSQL/Redis (rândurile de sus) cu **versiuni pinned** + dată verificare.
- **Consecințe:** evităm fixarea unor versiuni neverificate; scaffold-ul folosește
  constrângeri largi până la confirmare.
