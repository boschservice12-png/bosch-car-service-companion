# ADR 0003 — Document and photo storage

- **Status:** Accepted
- **Context:** Sensitive documents (damage photos, insurance certificates,
  receipts). They must be private, scanned, and served only temporarily.
- **Decision:**
  - **S3-compatible** storage behind an abstraction. Locally: **MinIO**; in
    production: a private bucket with no public access.
  - Upload flow: `POST /api/documents` → MIME + extension + size validation →
    stored with `scan_status=PENDING` → a Messenger job performs the **ClamAV
    scan** → `scan_status=CLEAN|INFECTED`. `INFECTED` and `PENDING` files are
    never served.
  - Served through a **short-lived signed URL**
    (`GET /api/documents/{id}/download-url`), with object-level authorisation.
  - Metadata in the `documents` table; content never in the database.
- **Consequences:** good isolation; a dependency on ClamAV. The
  `MalwareScanner` contract has a permissive implementation in development
  (`PermissiveDevScanner`), which logs an explicit warning on every file.

## Current state (2026-08)

Honoured, with two operational notes worth knowing:

- The production scanner is **fail-closed**: if clamd is unreachable, the upload
  is rejected rather than accepted unscanned. That makes a dead scanner stall
  document processing, which is why readiness carries a non-critical `scanner`
  probe — see [Monitoring §3](../../MONITORING.md).
- `STORAGE_DRIVER` selects the adapter at runtime (`local` or `s3`). The
  `S3Storage` adapter is exercised against in-stack MinIO on every readiness
  probe but has **not** been validated against real AWS S3 — see
  [Roadmap](../../ROADMAP.md).
