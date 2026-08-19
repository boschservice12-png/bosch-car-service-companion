# Demo and walkthrough

How to run the application and demonstrate the delivered flows, from **two
separate sessions: CUSTOMER and ADMIN**.

## What can be demonstrated

| Module | Customer | Service (admin) |
|---|---|---|
| **Deadlines** (roadworthiness / insurance / road tax / assistance) | Sees the calculated states (valid / expiring soon / expired), adds deadlines, attaches documents | Lists vehicles, validates deadlines, adds and attaches documents |
| **Service history** | Sees the published history for their own vehicles, downloads documents | Creates a draft → publishes; corrections keep the original visible |
| **Communication & quotes** | Sends messages and quote requests with attachments, accepts or declines a quote | Replies, sends the quote (amount) |
| **Roadside assistance** | Opens a request (location, problem, mobility, safety, phone, photo), cancels it | Takes it over (phone contact), changes its state |
| **Mobility** | Requests a replacement car / taxi / transport, cancels | Approves / provides / rejects |
| **Damage claim** | Opens a file (event, insurer, policy, photos) | Takes it over and tracks its state |
| **Taxes and duties** | Tracks annual taxes, edits them, marks payment declaratively (no files) | Adjusts the payment state |

Every action respects **object-level authorisation** (one customer never sees
another's data) and is written to the application's **audit log**.

## Demo accounts (after seeding)

| Role | Email | Password |
|---|---|---|
| Service (admin) | `admin@bcsc.ro` | `Demo1234!` |
| Customer | `client@bcsc.ro` | `Demo1234!` |

The demo customer has 2 vehicles (BMW 3 Series `MS01POP`, VW Golf `MS02POP`) with
deadlines in various states, a service history (one published record plus a
correction), a quote request with a quote sent (state **QUOTED**), and — for the
first vehicle — a **roadside assistance** request that has been taken over, an
approved **mobility** request, a **damage claim** in progress, and two **taxes**
(vehicle tax paid, environmental tax unpaid).

## Running

### Simplest — a single command (Docker)

Starts the **entire stack** (database + backend + both frontends, with demo data):

```bash
docker compose -f compose.demo.yaml up --build
```

Then: **Customer** http://localhost:3000 · **Admin** http://localhost:3001.
Details: [`../demo/README.md`](../demo/README.md).

### Option A — infrastructure in Docker, applications local

```bash
# 1) Infrastructure (PostgreSQL, MinIO, ClamAV, nginx → backend on :8080)
cd infrastructure/docker && docker compose up -d

# 2) Schema + demo data (inside the backend container)
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console app:demo:seed

# 3) Customer frontend (:3000)
cd ../../apps/customer-web && npm install && npm run dev

# 4) Admin frontend (:3001) — use a separate browser session
cd ../service-admin && npm install && npm run dev
```

### Option B — everything local, no Docker

```bash
# Backend (:8080) — needs PHP 8.3+ and a PostgreSQL reachable via DATABASE_URL
cd backend
composer install
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:demo:seed
php -S 127.0.0.1:8080 -t public

# Customer (:3000) and admin (:3001) frontends — as in Option A, steps 3-4
```

- Customer: <http://localhost:3000> · Admin: <http://localhost:3001>
- The frontends proxy `/api` to the backend (`NEXT_PUBLIC_API_BASE`, default
  `http://localhost:8080`).
- **Demo tip:** use two separate browser windows or profiles (or one normal and
  one incognito) so the CUSTOMER and ADMIN sessions do not overwrite each other.

## Demonstration script (about 5 minutes)

### 1. Deadlines (CUSTOMER → ADMIN → CUSTOMER)

1. **Customer** (`client@bcsc.ro`): *Vehicles → MS01POP*. The deadlines are
   visible: roadworthiness **valid**, insurance **expiring soon**, road tax
   **expired** (colour + text + days remaining).
2. Attach a document to a deadline (JPG/PNG/PDF, max 10 MB) → it shows "scanning
   in progress", then becomes downloadable.
3. **Admin** (`admin@bcsc.ro`): *Vehicles → MS01POP* → **Validate** a deadline
   entered by the customer.
4. **Customer**: reload — the deadline now shows as validated by the workshop.

### 2. Service history (ADMIN → CUSTOMER)

1. **Admin**: *Vehicle → Service history → + Add*. Fill in the date, mileage,
   type of work, description, parts, labour, total, warranty → **Save draft**.
2. Attach a document or photo, then **Publish**.
3. **Customer**: *Vehicle → Service history* — sees the published record and can
   download the document.
4. **Admin**: on a published record press **Create correction**, change it and
   publish. **Customer**: now sees both the original (marked "corrected") and the
   correction — nothing is lost.

### 3. Communication and quote request (CUSTOMER → ADMIN → CUSTOMER)

1. **Customer**: *Messages → + New* → type **Quote request**, subject, vehicle,
   message + attachment → **Send**.
2. **Admin**: *Messages* → open the thread → fill in the **quote amount** and
   details → **Send quote**.
3. **Customer**: the thread shows **Quote sent** plus the amount → **Accept**
   (or Decline). The demo conversation already starts in the *QUOTED* state, so it
   can be accepted directly.

### 4. Additional services (CUSTOMER → ADMIN)

From the customer's **Home** page ("Services" section) or the admin portal's top
bar:

- **Roadside assistance** (`/asistenta`): the customer opens a request (location,
  problem, mobility, safety, phone + photo); the **admin** takes it over — the
  state becomes "Taken over by the workshop" (direct phone contact).
- **Mobility** (`/mobilitate`): the customer requests a replacement car; the
  **admin** approves it or marks it as provided.
- **Damage claim** (`/daune`): the customer opens a file (event, insurer, policy,
  photos); the **admin** takes it over and tracks its state; documents download
  through authorised URLs.
- **Taxes and duties** (`/taxe`): the customer tracks annual taxes, edits them and
  marks payment declaratively — no file is uploaded, no receipt; the **admin** can
  adjust the payment state.

### 5. Isolation and audit (optional)

- Log in as a second customer and try to reach the first one's data — the response
  is **403**, on every module (deadlines, history, messages, assistance, mobility,
  claims, taxes).
- Every action above is written to `audit_logs` (before/after, actor, IP).

## Quick verification

```bash
./scripts/regression.sh
```

Or individually:

```bash
# Backend: tests (SQLite) + schema validation (PostgreSQL)
cd backend
php bin/console doctrine:schema:create --env=test && vendor/bin/phpunit
php bin/console doctrine:schema:validate           # against a migrated PostgreSQL

# Frontends (both applications)
cd ../apps/customer-web && npx tsc --noEmit && npx next lint && npx next build
cd ../service-admin     && npx tsc --noEmit && npx next lint && npx next build
```

For an end-to-end browser test across both sessions, see
[`../e2e/README.md`](../e2e/README.md).
