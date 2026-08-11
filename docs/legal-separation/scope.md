# Scope — legal and functional separation

The application is **single-tenant** and serves **one** Bosch Car Service
(SC Szkaliczki Service SRL). This document is the governance reference for what
gets built and what is forbidden.

## In scope
- Customer PWA + workshop admin portal.
- The 11 mandatory features (see
  [`../analysis/stage-1-functional-analysis.md`](../analysis/stage-1-functional-analysis.md)).
- Private documents, audit, consents, and the data subject's GDPR rights.

## Out of scope (forbidden without separate approval)
- Multi-tenant architecture, or a `tenant_id`.
- Multiple workshops on the same platform; a workshop network; a marketplace.
- A fleet module; a fleet-manager role; fleet reporting.
- Recurring subscriptions; SaaS billing; commission calculation.
- Insurance brokerage integration or insurance sales.
- Workshop capacity planning.
- ERP, internal estimates, stock, or parts management.
- A technical knowledge base; AI-assisted diagnostics.
- Copying source code from the earlier demo (`RedAssistance Core`).

## Procedure
Any new requirement matching the list above is labelled **`out-of-scope`** in the
PR or issue and does not enter the codebase without separate written approval.
At code review this rule is a mandatory gate.

## Messaging boundaries (UI)
- Service history **starts from the first visit to this workshop** — it is not a
  national VIN history.
- Deadline checking uses **entered and validated data**, not automated queries
  against official databases.
- Roadside assistance **does not replace the emergency number 112** in an
  immediate emergency.
- The damage claim file is **assistance and data collection**, not a claims or
  brokerage system.
- There is **no online payment**.
