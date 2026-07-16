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
