# Rulează demo-ul cu o singură comandă (Docker)

Pornește **întreaga aplicație** (bază de date + backend + ambele frontend-uri) local,
cu date demo deja încărcate. Singura cerință: **Docker Desktop** (sau Docker Engine +
plugin `compose`). Nu ai nevoie de PHP, Node sau PostgreSQL instalate separat.

## Pornire

Din rădăcina proiectului:

```bash
docker compose -f compose.demo.yaml up --build
```

Prima pornire durează câteva minute (construiește imaginea backend, instalează
dependențele frontend). La final ai:

| Aplicație | URL | Descriere |
|---|---|---|
| **Client** (PWA) | http://localhost:3000 | aplicația clientului |
| **Service / admin** | http://localhost:3001 | portalul service-ului |
| Backend API | http://localhost:8080/api/health | (opțional, pentru verificare) |

> **Sfat:** deschide clientul și adminul în **două profile de browser** diferite
> (sau unul normal + unul incognito), ca sesiunile să nu se suprascrie.

## Conturi demo

| Rol | Email | Parolă |
|---|---|---|
| Service (admin) | `admin@bcsc.ro` | `Demo1234!` |
| Client | `client@bcsc.ro` | `Demo1234!` |

După login, ambele interfețe sunt deja populate (scadențe, istoric service, oferte,
asistență rutieră, mobilitate, dosar de daună, taxe). Scenariul pas cu pas: `docs/DEMO.md`.

## Oprire / repornire

```bash
# Oprire (păstrează datele):            Ctrl+C, apoi
docker compose -f compose.demo.yaml down

# Ștergere completă (inclusiv baza de date, pentru un start curat):
docker compose -f compose.demo.yaml down -v
```

Datele demo se recreează automat la pornire (comanda `app:demo:seed` este idempotentă).

## Ce pornește

- **db** — PostgreSQL 16.
- **backend** — Symfony (server built-in pe `:8080`); la pornire aplică migrațiile și
  rulează `app:demo:seed`.
- **customer-web** — Next.js dev pe `:3000`, proxy `/api` → `backend:8080`.
- **service-admin** — Next.js dev pe `:3001`, proxy `/api` → `backend:8080`.

Scanarea antimalware a documentelor este asincronă; pentru demo, adapterul din mediul
non-prod marchează fișierele ca fiind curate, deci descărcarea funcționează imediat.
