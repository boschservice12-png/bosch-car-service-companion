# ADR 0001 — Modular monolith (nu microservicii)

- **Status:** Acceptat
- **Context:** Un singur service (single-tenant), echipă mică, cerință de livrare
  rapidă și operare simplă. Domeniul are ~15 module clar delimitate.
- **Decizie:** Backend-ul este un **modular monolith** Symfony. Fiecare domeniu
  este un modul separat sub `backend/src/<Modul>/`, cu straturi:
  `Domain` (entități, value objects, servicii de domeniu, contracte),
  `Application` (use-cases, DTO, handlers Messenger),
  `Infrastructure` (repository Doctrine, adaptoare),
  `Presentation` (controllere/API).
  Modulele comunică **doar prin contracte/interfețe publice**, nu prin acces
  direct la clasele interne ale altui modul. Interzis folderul generic `Utils`.
- **Consecințe:**
  - (+) Deploy simplu, tranzacții ACID, refactorizare ușoară.
  - (+) Granițe de modul verificabile (linter de dependențe pe namespace-uri).
  - (−) Necesită disciplină pentru a nu crea cuplaje ascunse.
- **Respins:** microservicii (overhead operațional nejustificat pentru un
  single-tenant), `tenant_id` / multi-tenant (explicit în afara perimetrului).
