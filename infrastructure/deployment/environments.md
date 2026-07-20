# Medii și CI/CD

## Medii separate
| Mediu | Scop | Note |
|---|---|---|
| **local** | dezvoltare | docker-compose, MinIO, transporturi OTP/email false |
| **test/staging** | QA, E2E, demo | date demo realiste, provideri sandbox |
| **production** | producție | secrete în secret manager, backup zilnic, monitorizare |

## Pipeline CI/CD (vezi `.github/workflows/ci.yml`)
1. **build** — imagini backend + frontend;
2. **lint** — PHPStan, ESLint, `tsc --noEmit`, OpenAPI lint;
3. **teste** — unit + integrare + autorizare + E2E;
4. **scanare** — dependențe & secrete;
5. **deploy** — staging automat, producție cu aprobare.

## Reguli
- fără secrete în repo; `.env` doar local;
- fiecare PR trece lint+teste înainte de merge;
- migrațiile rulează controlat la deploy (nu automat pe producție fără gate).

## Configurație pilot (vezi `docs/PILOT_READINESS.md`)
- **Storage**: `STORAGE_DRIVER=local` (dev/demo, volum persistent) sau `s3`
  (producție, cu `S3_ENDPOINT` / `S3_BUCKET` / `S3_KEY` / `S3_SECRET` / `S3_REGION`).
- **Readiness vs. liveness**: orchestratorul folosește `GET /api/health/ready`
  (deep — bază, migrații, storage, secrete → `503` la dependență critică jos)
  pentru rotație, și `GET /api/health` (liveness pur) pentru restart. Nu legați
  restart-ul de readiness.
- **APP_SECRET** trebuie setat real — readiness pică pe o valoare implicită/`change`.
- **Worker Messenger** rulează ca serviciu separat (`messenger:consume async`);
  fără el documentele rămân `PENDING`.
- `LEGACY_PLATE_CLAIM_ENABLED=false` — accesul la vehicul doar cu cod de activare.
