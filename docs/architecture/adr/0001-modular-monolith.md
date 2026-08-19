# ADR 0001 — Modular monolith (not microservices)

- **Status:** Accepted
- **Context:** A single workshop (single-tenant), a small team, a requirement to
  ship quickly and operate simply. The domain has roughly 15 clearly separated
  modules.
- **Decision:** The backend is a Symfony **modular monolith**. Each domain is a
  separate module under `backend/src/<Module>/`, with layers:
  `Domain` (entities, value objects, domain services, contracts),
  `Application` (use cases, DTOs, Messenger handlers),
  `Infrastructure` (Doctrine repositories, adapters),
  `Presentation` (controllers/API).
  Modules communicate **only through public contracts and interfaces**, never by
  reaching into another module's internal classes. A generic `Utils` folder is
  forbidden.
- **Consequences:**
  - (+) Simple deployment, ACID transactions, easy refactoring.
  - (+) Module boundaries are checkable (a namespace dependency lint).
  - (−) Requires discipline to avoid hidden coupling.
- **Rejected:** microservices (operational overhead unjustified for a
  single-tenant system), and `tenant_id` / multi-tenancy (explicitly
  [out of scope](../../legal-separation/scope.md)).

**Current state (2026-08):** honoured. The module layout is as described; see
[Architecture §2](../../ARCHITECTURE.md) for the module list.
