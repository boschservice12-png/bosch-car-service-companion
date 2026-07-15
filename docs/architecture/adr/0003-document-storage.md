# ADR 0003 — Storage documente și fotografii

- **Status:** Acceptat
- **Context:** Documente sensibile (poze daună, RCA, chitanțe). Trebuie private,
  scanate, servite temporar.
- **Decizie:**
  - Storage **S3-compatible**, abstractizat prin Flysystem. Local: **MinIO**;
    producție: bucket privat (fără acces public).
  - Fluxul de upload: `POST /api/documents` → validare MIME+extensie+dimensiune →
    stocare cu `scan_status=PENDING` → job Messenger de **scanare ClamAV** →
    `scan_status=CLEAN|INFECTED`. Fișierele `INFECTED`/`PENDING` nu se servesc.
  - Servire prin **URL temporar semnat** (`GET /api/documents/{id}/download-url`),
    cu expirare scurtă și verificare de autorizare la nivel de obiect.
  - Metadate în tabela `documents`; conținutul niciodată în DB.
- **Consecințe:** izolare bună; dependență de ClamAV (adaptor `MalwareScannerInterface`
  cu implementare no-op în dev dacă scannerul lipsește, semnalat în log).
