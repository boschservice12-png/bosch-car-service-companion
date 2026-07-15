# Perimetru — separare legală și funcțională

Aplicația este **single-tenant** și deservește **un singur** Bosch Car Service
(SC Szkaliczki Service SRL). Acest document este referința de guvernanță pentru
ce se implementează și ce este interzis.

## În perimetru
- Aplicație client PWA (RO) + portal admin service (RO).
- Cele 11 funcționalități obligatorii (vezi `../analysis/etapa-1-analiza-functionala.md`).
- Documente private, audit, consimțăminte, drepturi GDPR ale persoanei vizate.

## În afara perimetrului (interzis fără aprobare separată)
- Arhitectură multi-tenant / `tenant_id`.
- Mai multe service-uri pe aceeași platformă; rețea de service-uri; marketplace.
- Modul de flotă; rol de manager de flotă; raportare de flotă.
- Abonamente recurente; facturare SaaS; calcul de comisioane.
- Integrare de brokeraj sau vânzare de asigurări.
- Planificarea capacității atelierului.
- ERP, deviz intern, stocuri, gestiune piese.
- Bază de cunoștințe tehnice; diagnostic asistat de AI.
- Copierea codului sursă din demo-ul anterior (`RedAssistance Core`).

## Procedură
Orice cerință nouă care se potrivește listei de mai sus se marchează în PR/issue
ca **`în-afara-perimetrului`** și nu intră în cod fără aprobare scrisă separată.
La code review, această regulă este un gate obligatoriu.

## Delimitări de mesaj (UI)
- Istoricul de service **începe de la prima intrare în service** — nu este istoric
  național VIN.
- Verificarea scadențelor se face pe **date introduse și validate**, nu prin
  interogare automată a bazelor oficiale.
- Asistența rutieră **nu înlocuiește 112** în pericol imediat.
- Dosarul de daună este **asistență și colectare de date**, nu sistem de daună/brokeraj.
- Nu există **plată online**.
