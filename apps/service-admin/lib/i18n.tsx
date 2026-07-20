'use client';

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';

/**
 * Internaționalizare fără dependențe: româna este limba OFICIALĂ și cheia
 * dicționarelor — maghiara și engleza traduc textul românesc. O traducere
 * lipsă cade înapoi pe română (aplicația nu se strică niciodată).
 *
 * Folosire:  const t = useT();  →  t('Taxe și impozite')
 * Etichetele venite din backend (statusLabel etc.) sunt tot românești,
 * deci se traduc la fel:  t(tax.statusLabel).
 */
export type Locale = 'ro' | 'hu' | 'en';

const STORAGE_KEY = 'bcsc.locale';

export const LOCALE_NAMES: Record<Locale, string> = { ro: 'RO', hu: 'HU', en: 'EN' };

type Dict = Record<string, string>;

const HU: Dict = {
  // Comune
  'Vehicule': 'Járművek',
  'Vehicule →': 'Járművek →',
  'Mesaje': 'Üzenetek',
  'Se încarcă…': 'Betöltés…',
  'Se încarcă': 'Betöltés',
  'A apărut o eroare.': 'Hiba történt.',
  'Reîncearcă': 'Újra',
  'Eroare la încărcare.': 'Betöltési hiba.',
  'Trimitere eșuată.': 'A küldés nem sikerült.',
  'Operațiune eșuată.': 'A művelet nem sikerült.',
  'Încărcare eșuată.': 'A feltöltés nem sikerült.',
  'Descărcare eșuată.': 'A letöltés nem sikerült.',
  'Actualizare eșuată.': 'A frissítés nem sikerült.',
  'Validare eșuată.': 'A jóváhagyás nem sikerült.',
  'Adăugare eșuată.': 'A hozzáadás nem sikerült.',
  'Creare eșuată.': 'A létrehozás nem sikerült.',
  'Renunță': 'Mégse',
  'Salvează': 'Mentés',
  'Se salvează…': 'Mentés…',
  'Se trimite…': 'Küldés…',
  'Trimite': 'Küldés',
  'Adaugă': 'Hozzáadás',
  'Ieșire': 'Kijelentkezés',
  'opțional': 'opcionális',
  'și': 'és',
  'Descarcă': 'Letöltés',
  'document': 'dokumentum',
  'în curs de scanare': 'vizsgálat folyamatban',
  'Tip': 'Típus',
  'Da': 'Igen',
  'Nu': 'Nem',
  'Documente:': 'Dokumentumok:',
  'Email': 'E-mail',
  'Parolă': 'Jelszó',
  'Vehicul:': 'Jármű:',
  'Notă:': 'Megjegyzés:',
  'Notă internă (opțional)…': 'Belső megjegyzés (opcionális)…',
  'Actualizează starea': 'Állapot frissítése',

  // Etichete backend (statusuri și tipuri)
  'Neplătită': 'Nincs befizetve',
  'Parțial plătită': 'Részben befizetve',
  'Plătită': 'Befizetve',
  'Restantă': 'Késedelmes',
  'Impozit auto': 'Gépjárműadó',
  'Taxă de mediu': 'Környezetvédelmi díj',
  'Altă taxă': 'Egyéb adó',
  'Ciornă': 'Piszkozat',
  'Publicat': 'Közzétéve',
  'Corectat': 'Javítva',
  'Trimis': 'Elküldve',
  'Trimisă': 'Elküldve',
  'Validată': 'Jóváhagyva',
  'Direcționată': 'Továbbítva',
  'În curs': 'Folyamatban',
  'Finalizată': 'Befejezve',
  'Anulată': 'Visszavonva',
  'Se poate deplasa': 'Mozgásképes',
  'Imobilizată': 'Mozgásképtelen',
  'În siguranță': 'Biztonságban',
  'Situație periculoasă': 'Veszélyes helyzet',
  'În analiză': 'Elbírálás alatt',
  'Informații necesare': 'További információ szükséges',
  'Ofertă trimisă': 'Ajánlat elküldve',
  'Acceptată': 'Elfogadva',
  'Refuzată': 'Elutasítva',
  'Închisă': 'Lezárva',
  'Închis': 'Lezárva',
  'Deschisă': 'Nyitott',
  'Așteaptă clientul': 'Ügyfélre vár',
  'Așteaptă service-ul': 'Szervizre vár',
  'Client': 'Ügyfél',
  'Service': 'Szerviz',
  'Necunoscut': 'Ismeretlen',
  'Valabil': 'Érvényes',
  'Expiră curând': 'Hamarosan lejár',
  'Expirat': 'Lejárt',
  'ITP': 'Műszaki vizsga (ITP)',
  'Taxă de drum': 'Útdíj',
  'Asistență rutieră': 'Autómentés',
  'Mașină de înlocuire': 'Cserejármű',
  'Taxi': 'Taxi',
  'Transport persoane': 'Személyszállítás',
  'Cazare': 'Szállás',
  'Altă solicitare': 'Egyéb igénylés',
  'Contactat': 'Felvettük a kapcsolatot',
  'Confirmată': 'Megerősítve',
  'Indisponibilă': 'Nem elérhető',
  'Documente lipsă': 'Hiányzó dokumentumok',
  'Dosar deschis': 'Akta megnyitva',

  // Scadențe (DeadlineBadge)
  'fără dată': 'nincs dátum',
  'expiră azi': 'ma jár le',
  'expirat de {n} zile': '{n} napja lejárt',
  '{n} zile rămase': '{n} nap van hátra',

  // Atașamente și documente
  'Fișier gol.': 'Üres fájl.',
  'Fișierul depășește {n} MB.': 'A fájl meghaladja a {n} MB-ot.',
  'Fișierul depășește limita de {n} MB.': 'A fájl meghaladja a {n} MB-os korlátot.',
  'Tip de fișier nepermis (JPG, PNG, WEBP, PDF).': 'Nem engedélyezett fájltípus (JPG, PNG, WEBP, PDF).',
  'Tip de fișier nepermis. Acceptăm imagini (JPG, PNG, WEBP) și PDF.': 'Nem engedélyezett fájltípus. Képeket (JPG, PNG, WEBP) és PDF-et fogadunk el.',
  'Elimină {name}': '{name} eltávolítása',
  '📎 Adaugă atașament': '📎 Melléklet hozzáadása',
  'respins (malware)': 'elutasítva (kártevő)',
  'Niciun document ataşat.': 'Nincs csatolt dokumentum.',
  'Înlocuieşte documentul': 'Dokumentum cseréje',
  '📎 Ataşează document': '📎 Dokumentum csatolása',
  '📎 Atașează document / foto': '📎 Dokumentum / fotó csatolása',
  'JPG, PNG, WEBP sau PDF · max {n} MB': 'JPG, PNG, WEBP vagy PDF · max. {n} MB',

  // Autentificare
  'Portal Service': 'Szerviz portál',
  'Autentificare administrator.': 'Adminisztrátori bejelentkezés.',
  'Acest portal este doar pentru administratorii service-ului.':
    'Ez a portál kizárólag a szerviz adminisztrátorai számára érhető el.',
  'Se conectează…': 'Bejelentkezés…',
  'Intră în portal': 'Belépés a portálra',

  // Panou (dashboard)
  '📥 Import clienți (Excel)': '📥 Ügyfélimport (Excel)',
  '🧰 Cereri ofertă': '🧰 Ajánlatkérések',
  '💬 Mesaje': '💬 Üzenetek',
  '🆘 Asistență': '🆘 Autómentés',
  '🚕 Mobilitate': '🚕 Mobilitás',
  '📋 Daune': '📋 Károk',
  '🧾 Taxe': '🧾 Adók',
  'Nu am putut încărca vehiculele.': 'Nem sikerült betölteni a járműveket.',
  'Niciun vehicul înregistrat': 'Nincs regisztrált jármű',
  'Nume proprietar': 'Tulajdonos neve',
  'ex. Popescu': 'pl. Popescu',
  'Nr. înmatriculare': 'Rendszám',
  'ex. MS 01 POP': 'pl. MS 01 POP',
  'ex. WBA3A5…': 'pl. WBA3A5…',
  '{n} din {m} vehicule': '{n} / {m} jármű',
  'Șterge filtrele': 'Szűrők törlése',
  'Niciun vehicul găsit': 'Nem található jármű',
  'Niciun rezultat pentru filtrele introduse — căutați după numele proprietarului, numărul de înmatriculare sau VIN.':
    'Nincs találat a megadott szűrőkre — keressen a tulajdonos neve, a rendszám vagy az alvázszám (VIN) alapján.',
  'Scadențe →': 'Lejáratok →',

  // Import Excel
  '← Panou': '← Vezérlőpult',
  'Import date din Excel': 'Adatimport Excelből',
  'Pasul 1 — Clienți și vehicule': '1. lépés — Ügyfelek és járművek',
  'Încărcați tabelul cu proprietari și vehicule (.xlsx sau .csv). Coloane:':
    'Töltse fel a tulajdonosok és járművek táblázatát (.xlsx vagy .csv). Oszlopok:',
  'Reimportul aceluiași fișier nu creează dubluri.':
    'Ugyanazon fájl újraimportálása nem hoz létre duplikátumokat.',
  'Importul a eșuat.': 'Az importálás nem sikerült.',
  'Fișier (.xlsx sau .csv, max. 5 MB)': 'Fájl (.xlsx vagy .csv, max. 5 MB)',
  'Se importă…': 'Importálás…',
  'Importă': 'Importálás',
  'Raport import': 'Importjelentés',
  'Rânduri procesate:': 'Feldolgozott sorok:',
  'Proprietari noi:': 'Új tulajdonosok:',
  'Vehicule noi:': 'Új járművek:',
  'actualizate:': 'frissítve:',
  'Legături proprietate create:': 'Létrehozott tulajdonosi kapcsolatok:',
  'Rânduri cu probleme ({n})': 'Hibás sorok ({n})',
  'Rândul {n}:': '{n}. sor:',
  'Toate rândurile au fost importate fără erori. ✔': 'Minden sor hiba nélkül importálva. ✔',
  'Pasul 2 — Istoric reparații': '2. lépés — Javítási előzmények',
  'Tabelul cu istoricul de reparații, legat de vehicule prin VIN (importați întâi clienții). Coloane:':
    'A javítási előzmények táblázata, a járművekhez alvázszám (VIN) alapján kapcsolva (először az ügyfeleket importálja). Oszlopok:',
  'Rândurile complete devin vizibile clientului imediat; cele incomplete rămân ciorne. Reimportul nu creează dubluri.':
    'A hiánytalan sorok azonnal láthatóvá válnak az ügyfél számára; a hiányosak piszkozatként maradnak. Az újraimportálás nem hoz létre duplikátumokat.',
  'Fișier istoric (.xlsx sau .csv, max. 5 MB)': 'Előzményfájl (.xlsx vagy .csv, max. 5 MB)',
  'Importă istoricul': 'Előzmények importálása',
  'Raport istoric': 'Előzményjelentés',
  'Publicate (vizibile clientului):': 'Közzétéve (ügyfél számára látható):',
  'Ciorne (de completat):': 'Piszkozatok (kiegészítendő):',
  'Sărite (existau deja):': 'Kihagyva (már létezett):',

  // Vehicul — scadențe
  '← Vehicule': '← Járművek',
  'Scadențe': 'Lejáratok',
  '🧾 Istoric service': '🧾 Szervizelőzmények',
  'Nicio scadență introdusă.': 'Nincs megadott lejárat.',
  'validat': 'jóváhagyva',
  'nevalidat': 'nincs jóváhagyva',
  'Verificare ITP (RAR) ↗': 'Műszaki vizsga ellenőrzése (RAR) ↗',
  'Verificare RCA (AIDA) ↗': 'RCA ellenőrzése (AIDA) ↗',
  'Verificare taxă de drum (eRovinieta) ↗': 'Útdíj ellenőrzése (eRovinieta) ↗',
  'Validează': 'Jóváhagyás',
  'Adaugă scadență (validată de service)': 'Lejárat hozzáadása (szerviz által jóváhagyva)',
  'Data expirării': 'Lejárat dátuma',

  // Istoric service
  '← Vehicul': '← Jármű',
  'Istoric service': 'Szervizelőzmények',
  '+ Adaugă': '+ Hozzáadás',
  'Înregistrare nouă (ciornă)': 'Új bejegyzés (piszkozat)',
  'Salvează ciorna': 'Piszkozat mentése',
  'Nicio înregistrare de service.': 'Nincs szervizbejegyzés.',
  'O înregistrare publicată nu poate fi rescrisă. Pentru modificări folosiți „Creează corecție" — atât originalul, cât și corecția rămân vizibile.':
    'A közzétett bejegyzés nem írható át. Módosításhoz használja a „Javítás létrehozása" gombot — az eredeti és a javítás is látható marad.',
  'Lucrare': 'Munkálat',
  'Piese:': 'Alkatrészek:',
  'Manoperă:': 'Munkadíj:',
  'Total:': 'Összesen:',
  'Garanție:': 'Garancia:',
  'corecție': 'javítás',
  'corectat': 'javítva',
  'Editează ciorna': 'Piszkozat szerkesztése',
  'Editează': 'Szerkesztés',
  'Publică': 'Közzététel',
  'Motivul corecției (obligatoriu):': 'A javítás oka (kötelező):',
  'Creează corecție': 'Javítás létrehozása',
  'Data': 'Dátum',
  'Kilometraj (km)': 'Kilométeróra-állás (km)',
  'Tipul lucrării': 'A munkálat típusa',
  'Ex: Revizie periodică': 'Pl.: Időszakos szerviz',
  'Lucrări efectuate': 'Elvégzett munkálatok',
  'Piese (rezumat)': 'Alkatrészek (összegzés)',
  'Manoperă (RON)': 'Munkadíj (RON)',
  'Total (RON)': 'Összesen (RON)',
  'Garanție': 'Garancia',
  'Ex: 12 luni / 20.000 km': 'Pl.: 12 hónap / 20 000 km',

  // Taxe și impozite
  'Taxe și impozite': 'Adók és illetékek',
  '← Taxe și impozite': '← Adók és illetékek',
  'Nicio taxă înregistrată': 'Nincs rögzített adó',
  'Scadență:': 'Esedékesség:',
  'Stare de plată': 'Fizetési állapot',
  'Marchează plătită': 'Megjelölés befizetettként',
  'Marchează neplătită': 'Megjelölés befizetetlenként',

  // Mesaje
  'Mesaje & oferte': 'Üzenetek és ajánlatok',
  'Nicio conversație': 'Nincs beszélgetés',
  '← Mesaje': '← Üzenetek',
  'Redeschide conversația': 'Beszélgetés újranyitása',
  'Închide conversația': 'Beszélgetés lezárása',
  'Răspunde': 'Válasz',
  'Scrieți un mesaj…': 'Írjon üzenetet…',

  // Cereri ofertă
  'Cereri ofertă': 'Ajánlatkérések',
  '← Cereri ofertă': '← Ajánlatkérések',
  'Cererile de ofertă ale clienților (ciornele nu apar aici).':
    'Az ügyfelek ajánlatkérései (a piszkozatok itt nem jelennek meg).',
  'Nicio cerere de ofertă': 'Nincs ajánlatkérés',
  'Cererile trimise de clienți apar aici.': 'Az ügyfelek által elküldött kérelmek itt jelennek meg.',
  'Cerere de ofertă': 'Ajánlatkérés',
  'Kilometraj:': 'Kilométeróra-állás:',
  'Problemă:': 'Probléma:',
  'Când apare:': 'Mikor jelentkezik:',
  'Conducibilă:': 'Vezethető:',
  'Martori:': 'Visszajelző lámpák:',
  'Contact preferat:': 'Kívánt kapcsolattartás:',
  'Interval:': 'Időszak:',
  'Răspunsuri trimise': 'Elküldött válaszok',
  'Acțiuni': 'Műveletek',
  'Preia în analiză': 'Elbírálásba vétel',
  'Cere informații': 'Információkérés',
  'Închide': 'Lezárás',
  'Trimite oferta': 'Ajánlat küldése',
  'Estimare, piese, manoperă, termen…': 'Becslés, alkatrészek, munkadíj, határidő…',
  'Trimite oferta (REPLIED)': 'Ajánlat küldése (REPLIED)',
  'Tranziție nepermisă.': 'Nem engedélyezett állapotváltás.',
  'Trimiterea răspunsului a eșuat.': 'A válasz elküldése nem sikerült.',

  // Asistență rutieră
  '← Asistență rutieră': '← Autómentés',
  'Nicio cerere de asistență': 'Nincs segítségkérelem',
  'Locație:': 'Helyszín:',
  'Mobilitate:': 'Mozgásképesség:',
  'Siguranță:': 'Biztonság:',
  'Telefon:': 'Telefon:',
  'Direcționează (contact telefonic)': 'Továbbítás (telefonos kapcsolat)',
  'Finalizează': 'Befejezés',
  'Anulează': 'Visszavonás',

  // Mobilitate
  'Mobilitate': 'Mobilitás',
  '← Mobilitate': '← Mobilitás',
  'Nicio solicitare de mobilitate': 'Nincs mobilitási igénylés',
  ' · pentru {date}': ' · {date} napra',
  'Detalii:': 'Részletek:',
  'Data preferată:': 'Kívánt dátum:',
  'Client contactat': 'Ügyfél értesítve',
  'Confirmă': 'Megerősítés',

  // Daune
  'Dosare de daună': 'Kárakták',
  '← Dosare de daună': '← Kárakták',
  'Niciun dosar de daună': 'Nincs kárakta',
  'Dosar de daună': 'Kárakta',
  'Cere documente': 'Dokumentumok bekérése',
  'Data:': 'Dátum:',
  'Loc:': 'Helyszín:',
  'Descriere:': 'Leírás:',
  'Poliță:': 'Kötvény:',
  'Fotografii și documente:': 'Fényképek és dokumentumok:',

  // Securitate / 2FA (P0-06)
  '🔐 Securitate': '🔐 Biztonság',
  '← Înapoi': '← Vissza',
  'Securitatea contului': 'A fiók biztonsága',
  'Autentificare în doi pași (2FA)': 'Kétlépcsős azonosítás (2FA)',
  'Nu am putut încărca datele contului.': 'Nem sikerült betölteni a fiók adatait.',
  'Verificare în doi pași': 'Kétlépcsős ellenőrzés',
  'Introduceți codul din aplicația de autentificare (Google/Microsoft Authenticator).': 'Írja be a hitelesítő alkalmazás kódját (Google/Microsoft Authenticator).',
  'Introduceți unul dintre codurile de rezervă primite la activarea 2FA.': 'Írja be a 2FA aktiválásakor kapott tartalék kódok egyikét.',
  'Cod din aplicație (6 cifre)': 'Kód az alkalmazásból (6 számjegy)',
  'Cod de rezervă': 'Tartalék kód',
  'Se verifică…': 'Ellenőrzés…',
  'Verifică': 'Ellenőrzés',
  'Folosesc codul din aplicație': 'Az alkalmazás kódját használom',
  'Nu am telefonul — folosesc un cod de rezervă': 'Nincs nálam a telefon — tartalék kódot használok',
  '2FA este ACTIV pe acest cont — la fiecare login se cere codul din aplicația de autentificare.': 'A 2FA AKTÍV ezen a fiókon — minden belépéskor a hitelesítő alkalmazás kódja szükséges.',
  'Se dezactivează…': 'Kikapcsolás…',
  'Dezactivează 2FA': '2FA kikapcsolása',
  'Protejați contul de administrator cu un al doilea factor: un cod generat de aplicația de autentificare (Google/Microsoft Authenticator, Authy).': 'Védje az admin fiókot második faktorral: a hitelesítő alkalmazás (Google/Microsoft Authenticator, Authy) által generált kóddal.',
  'Confirmați parola contului': 'Erősítse meg a fiók jelszavát',
  'Se pregătește…': 'Előkészítés…',
  'Activează 2FA': '2FA bekapcsolása',
  'Pasul 2: adăugați contul în aplicația de autentificare': '2. lépés: adja hozzá a fiókot a hitelesítő alkalmazáshoz',
  'Scanați codul QR din aplicație folosind linkul de mai jos sau introduceți secretul manual, apoi confirmați cu primul cod generat.': 'Olvassa be az alábbi linket az alkalmazással, vagy írja be a titkos kulcsot kézzel, majd erősítse meg az első generált kóddal.',
  'Secret (introducere manuală)': 'Titkos kulcs (kézi bevitel)',
  'Link de configurare (otpauth)': 'Beállító link (otpauth)',
  'Se confirmă…': 'Megerősítés…',
  'Confirmă și activează': 'Megerősítés és bekapcsolás',
  '2FA activat': '2FA bekapcsolva',
  'Salvați codurile de rezervă de mai jos într-un loc sigur — se afișează O SINGURĂ DATĂ. Fiecare cod funcționează o singură dată, dacă pierdeți accesul la aplicația de autentificare.': 'Mentse el az alábbi tartalék kódokat biztonságos helyre — CSAK EGYSZER jelennek meg. Minden kód egyszer használható, ha elveszíti a hozzáférést a hitelesítő alkalmazáshoz.',
  'Am salvat codurile de rezervă': 'Elmentettem a tartalék kódokat',
  // Mesaje de eroare de la backend (2FA)
  'Parola introdusă nu este corectă.': 'A megadott jelszó nem helyes.',
  'Cod TOTP invalid sau expirat.': 'Érvénytelen vagy lejárt TOTP-kód.',
  'Cod TOTP sau cod de rezervă invalid.': 'Érvénytelen TOTP- vagy tartalék kód.',
  'Este necesară verificarea în doi factori.': 'Kétlépcsős ellenőrzés szükséges.',
  'Contul de service trebuie să activeze 2FA înainte de a folosi portalul.': 'A szerviz fióknak be kell kapcsolnia a 2FA-t a portál használata előtt.',
  '2FA este deja activ pe acest cont.': 'A 2FA már aktív ezen a fiókon.',
  '2FA nu este activat pe acest cont.': 'A 2FA nincs bekapcsolva ezen a fiókon.',

  // Liste mari (P2-03)
  'Afișează încă {n} (din {m} rămase)': 'Még {n} megjelenítése ({m} van hátra)',

  // Import ASM (.xls)
  'Sărite — VIN nevalid (rămân doar în ASM):': 'Kihagyva — érvénytelen VIN (csak az ASM-ben maradnak):',
  'Parteneri fără vehicul:': 'Jármű nélküli partnerek:',
  'Încărcați „lista parteneri" din ASM (.xls, .xlsx sau .csv). Coloane recunoscute:': 'Töltse fel az ASM „lista parteneri" exportját (.xls, .xlsx vagy .csv). Felismert oszlopok:',
  'sau': 'vagy',
  'Fișier (.xls, .xlsx sau .csv, max. 10 MB)': 'Fájl (.xls, .xlsx vagy .csv, max. 10 MB)',
  'Fișier istoric (.xls, .xlsx sau .csv, max. 10 MB)': 'Előzményfájl (.xls, .xlsx vagy .csv, max. 10 MB)',
  'Pasul 2 — Istoric reparații → carte service': '2. lépés — Javítási lista → szervizkönyv',
  'Exportul „PersonalManopere" din ASM sau orice tabel legat prin VIN (importați întâi clienții). Coloane recunoscute:': 'Az ASM „PersonalManopere" exportja vagy bármely VIN-nel kapcsolt tábla (előbb az ügyfeleket importálja). Felismert oszlopok:',
  'Pasul 3 — Report ITP/RCA → scadențe': '3. lépés — ITP/RCA riport → lejáratok',
  'Raportul de alerte din ASM („report itp . rca"). Coloane recunoscute:': 'Az ASM riasztás-riportja („report itp . rca"). Felismert oszlopok:',
  'Doar alertele ITP/RCA devin scadențe (restul se sar); vehiculul se identifică după număr, iar reimportul actualizează fără dubluri. Scadențele apar apoi în Alerte (client) și 🔔 Notificări.': 'Csak az ITP/RCA riasztásokból lesz lejárat (a többit kihagyja); az autót rendszám alapján azonosítja, az újraimport duplikálás nélkül frissít. A lejáratok az Alerte (ügyfél) és a 🔔 Értesítések alatt jelennek meg.',
  'Fișier raport (.xls, .xlsx sau .csv, max. 10 MB)': 'Riportfájl (.xls, .xlsx vagy .csv, max. 10 MB)',
  'Importă scadențele': 'Lejáratok importálása',
  'Raport scadențe': 'Lejárat-jelentés',
  'Scadențe create:': 'Létrehozott lejáratok:',
  'Neschimbate (sărite):': 'Változatlan (kihagyva):',
  'alte alerte (nu ITP/RCA):': 'egyéb riasztás (nem ITP/RCA):',

  // Notificări de scadență (P1-03)
  '🔔 Notificări': '🔔 Értesítések',
  'Notificări de scadență': 'Lejárati értesítések',
  'Mesajul se deschide gata scris în WhatsApp-ul service-ului sau în email — îl trimiteți cu un click, iar trimiterea se consemnează.':
    'Az üzenet előre megírva nyílik meg a szerviz WhatsAppjában vagy e-mailben — egy kattintással elküldi, a küldés pedig rögzül.',
  'Fereastra de timp': 'Időablak',
  'Următoarele {n} zile': 'A következő {n} nap',
  'Nicio scadență în fereastra aleasă': 'Nincs lejárat a kiválasztott időablakban',
  'în {n} zile': '{n} nap múlva',
  'expirat': 'lejárt',
  'fără telefon': 'nincs telefonszám',
  'Notificat ultima dată: {date}': 'Utoljára értesítve: {date}',
  '📲 WhatsApp': '📲 WhatsApp',
  '✉️ Email': '✉️ E-mail',
};

const EN: Dict = {
  // Comune
  'Vehicule': 'Vehicles',
  'Vehicule →': 'Vehicles →',
  'Mesaje': 'Messages',
  'Se încarcă…': 'Loading…',
  'Se încarcă': 'Loading',
  'A apărut o eroare.': 'Something went wrong.',
  'Reîncearcă': 'Retry',
  'Eroare la încărcare.': 'Failed to load.',
  'Trimitere eșuată.': 'Failed to send.',
  'Operațiune eșuată.': 'Operation failed.',
  'Încărcare eșuată.': 'Upload failed.',
  'Descărcare eșuată.': 'Download failed.',
  'Actualizare eșuată.': 'Update failed.',
  'Validare eșuată.': 'Validation failed.',
  'Adăugare eșuată.': 'Failed to add.',
  'Creare eșuată.': 'Failed to create.',
  'Renunță': 'Cancel',
  'Salvează': 'Save',
  'Se salvează…': 'Saving…',
  'Se trimite…': 'Sending…',
  'Trimite': 'Send',
  'Adaugă': 'Add',
  'Ieșire': 'Sign out',
  'opțional': 'optional',
  'și': 'and',
  'Descarcă': 'Download',
  'document': 'document',
  'în curs de scanare': 'scan in progress',
  'Tip': 'Type',
  'Da': 'Yes',
  'Nu': 'No',
  'Documente:': 'Documents:',
  'Email': 'Email',
  'Parolă': 'Password',
  'Vehicul:': 'Vehicle:',
  'Notă:': 'Note:',
  'Notă internă (opțional)…': 'Internal note (optional)…',
  'Actualizează starea': 'Update status',

  // Etichete backend (statusuri și tipuri)
  'Neplătită': 'Unpaid',
  'Parțial plătită': 'Partially paid',
  'Plătită': 'Paid',
  'Restantă': 'Overdue',
  'Impozit auto': 'Vehicle tax',
  'Taxă de mediu': 'Environmental tax',
  'Altă taxă': 'Other tax',
  'Ciornă': 'Draft',
  'Publicat': 'Published',
  'Corectat': 'Corrected',
  'Trimis': 'Submitted',
  'Trimisă': 'Submitted',
  'Validată': 'Validated',
  'Direcționată': 'Forwarded',
  'În curs': 'In progress',
  'Finalizată': 'Completed',
  'Anulată': 'Cancelled',
  'Se poate deplasa': 'Drivable',
  'Imobilizată': 'Immobilized',
  'În siguranță': 'Safe',
  'Situație periculoasă': 'Dangerous situation',
  'În analiză': 'Under review',
  'Informații necesare': 'Information needed',
  'Ofertă trimisă': 'Quote sent',
  'Acceptată': 'Accepted',
  'Refuzată': 'Declined',
  'Închisă': 'Closed',
  'Închis': 'Closed',
  'Deschisă': 'Open',
  'Așteaptă clientul': 'Waiting for customer',
  'Așteaptă service-ul': 'Waiting for service',
  'Client': 'Customer',
  'Service': 'Service',
  'Necunoscut': 'Unknown',
  'Valabil': 'Valid',
  'Expiră curând': 'Expiring soon',
  'Expirat': 'Expired',
  'ITP': 'Inspection (ITP)',
  'Taxă de drum': 'Road tax',
  'Asistență rutieră': 'Roadside assistance',
  'Mașină de înlocuire': 'Replacement car',
  'Taxi': 'Taxi',
  'Transport persoane': 'Passenger transport',
  'Cazare': 'Accommodation',
  'Altă solicitare': 'Other request',
  'Contactat': 'Contacted',
  'Confirmată': 'Confirmed',
  'Indisponibilă': 'Unavailable',
  'Documente lipsă': 'Documents missing',
  'Dosar deschis': 'File opened',

  // Scadențe (DeadlineBadge)
  'fără dată': 'no date',
  'expiră azi': 'expires today',
  'expirat de {n} zile': 'expired {n} days ago',
  '{n} zile rămase': '{n} days left',

  // Atașamente și documente
  'Fișier gol.': 'Empty file.',
  'Fișierul depășește {n} MB.': 'File exceeds {n} MB.',
  'Fișierul depășește limita de {n} MB.': 'File exceeds the {n} MB limit.',
  'Tip de fișier nepermis (JPG, PNG, WEBP, PDF).': 'File type not allowed (JPG, PNG, WEBP, PDF).',
  'Tip de fișier nepermis. Acceptăm imagini (JPG, PNG, WEBP) și PDF.':
    'File type not allowed. We accept images (JPG, PNG, WEBP) and PDF.',
  'Elimină {name}': 'Remove {name}',
  '📎 Adaugă atașament': '📎 Add attachment',
  'respins (malware)': 'rejected (malware)',
  'Niciun document ataşat.': 'No document attached.',
  'Înlocuieşte documentul': 'Replace document',
  '📎 Ataşează document': '📎 Attach document',
  '📎 Atașează document / foto': '📎 Attach document / photo',
  'JPG, PNG, WEBP sau PDF · max {n} MB': 'JPG, PNG, WEBP or PDF · max {n} MB',

  // Autentificare
  'Portal Service': 'Service portal',
  'Autentificare administrator.': 'Administrator sign-in.',
  'Acest portal este doar pentru administratorii service-ului.':
    'This portal is for service administrators only.',
  'Se conectează…': 'Signing in…',
  'Intră în portal': 'Enter portal',

  // Panou (dashboard)
  '📥 Import clienți (Excel)': '📥 Client import (Excel)',
  '🧰 Cereri ofertă': '🧰 Quote requests',
  '💬 Mesaje': '💬 Messages',
  '🆘 Asistență': '🆘 Assistance',
  '🚕 Mobilitate': '🚕 Mobility',
  '📋 Daune': '📋 Damage claims',
  '🧾 Taxe': '🧾 Taxes',
  'Nu am putut încărca vehiculele.': 'Could not load vehicles.',
  'Niciun vehicul înregistrat': 'No vehicles registered',
  'Nume proprietar': 'Owner name',
  'ex. Popescu': 'e.g. Popescu',
  'Nr. înmatriculare': 'License plate',
  'ex. MS 01 POP': 'e.g. MS 01 POP',
  'ex. WBA3A5…': 'e.g. WBA3A5…',
  '{n} din {m} vehicule': '{n} of {m} vehicles',
  'Șterge filtrele': 'Clear filters',
  'Niciun vehicul găsit': 'No vehicles found',
  'Niciun rezultat pentru filtrele introduse — căutați după numele proprietarului, numărul de înmatriculare sau VIN.':
    'No results for the given filters — search by owner name, license plate, or VIN.',
  'Scadențe →': 'Deadlines →',

  // Import Excel
  '← Panou': '← Dashboard',
  'Import date din Excel': 'Import data from Excel',
  'Pasul 1 — Clienți și vehicule': 'Step 1 — Clients and vehicles',
  'Încărcați tabelul cu proprietari și vehicule (.xlsx sau .csv). Coloane:':
    'Upload the table of owners and vehicles (.xlsx or .csv). Columns:',
  'Reimportul aceluiași fișier nu creează dubluri.':
    'Re-importing the same file does not create duplicates.',
  'Importul a eșuat.': 'Import failed.',
  'Fișier (.xlsx sau .csv, max. 5 MB)': 'File (.xlsx or .csv, max. 5 MB)',
  'Se importă…': 'Importing…',
  'Importă': 'Import',
  'Raport import': 'Import report',
  'Rânduri procesate:': 'Rows processed:',
  'Proprietari noi:': 'New owners:',
  'Vehicule noi:': 'New vehicles:',
  'actualizate:': 'updated:',
  'Legături proprietate create:': 'Ownership links created:',
  'Rânduri cu probleme ({n})': 'Rows with problems ({n})',
  'Rândul {n}:': 'Row {n}:',
  'Toate rândurile au fost importate fără erori. ✔': 'All rows were imported without errors. ✔',
  'Pasul 2 — Istoric reparații': 'Step 2 — Repair history',
  'Tabelul cu istoricul de reparații, legat de vehicule prin VIN (importați întâi clienții). Coloane:':
    'The repair history table, linked to vehicles by VIN (import clients first). Columns:',
  'Rândurile complete devin vizibile clientului imediat; cele incomplete rămân ciorne. Reimportul nu creează dubluri.':
    'Complete rows become visible to the customer immediately; incomplete ones remain drafts. Re-importing does not create duplicates.',
  'Fișier istoric (.xlsx sau .csv, max. 5 MB)': 'History file (.xlsx or .csv, max. 5 MB)',
  'Importă istoricul': 'Import history',
  'Raport istoric': 'History report',
  'Publicate (vizibile clientului):': 'Published (visible to customer):',
  'Ciorne (de completat):': 'Drafts (to complete):',
  'Sărite (existau deja):': 'Skipped (already existed):',

  // Vehicul — scadențe
  '← Vehicule': '← Vehicles',
  'Scadențe': 'Deadlines',
  '🧾 Istoric service': '🧾 Service history',
  'Nicio scadență introdusă.': 'No deadlines entered.',
  'validat': 'verified',
  'nevalidat': 'not verified',
  'Verificare ITP (RAR) ↗': 'Check inspection (RAR) ↗',
  'Verificare RCA (AIDA) ↗': 'Check RCA (AIDA) ↗',
  'Verificare taxă de drum (eRovinieta) ↗': 'Check road tax (eRovinieta) ↗',
  'Validează': 'Validate',
  'Adaugă scadență (validată de service)': 'Add deadline (validated by the service)',
  'Data expirării': 'Expiry date',

  // Istoric service
  '← Vehicul': '← Vehicle',
  'Istoric service': 'Service history',
  '+ Adaugă': '+ Add',
  'Înregistrare nouă (ciornă)': 'New record (draft)',
  'Salvează ciorna': 'Save draft',
  'Nicio înregistrare de service.': 'No service records.',
  'O înregistrare publicată nu poate fi rescrisă. Pentru modificări folosiți „Creează corecție" — atât originalul, cât și corecția rămân vizibile.':
    'A published record cannot be rewritten. To make changes use "Create correction" — both the original and the correction remain visible.',
  'Lucrare': 'Work',
  'Piese:': 'Parts:',
  'Manoperă:': 'Labor:',
  'Total:': 'Total:',
  'Garanție:': 'Warranty:',
  'corecție': 'correction',
  'corectat': 'corrected',
  'Editează ciorna': 'Edit draft',
  'Editează': 'Edit',
  'Publică': 'Publish',
  'Motivul corecției (obligatoriu):': 'Reason for the correction (required):',
  'Creează corecție': 'Create correction',
  'Data': 'Date',
  'Kilometraj (km)': 'Mileage (km)',
  'Tipul lucrării': 'Work type',
  'Ex: Revizie periodică': 'E.g. Periodic service',
  'Lucrări efectuate': 'Work performed',
  'Piese (rezumat)': 'Parts (summary)',
  'Manoperă (RON)': 'Labor (RON)',
  'Total (RON)': 'Total (RON)',
  'Garanție': 'Warranty',
  'Ex: 12 luni / 20.000 km': 'E.g. 12 months / 20,000 km',

  // Taxe și impozite
  'Taxe și impozite': 'Taxes & duties',
  '← Taxe și impozite': '← Taxes & duties',
  'Nicio taxă înregistrată': 'No taxes recorded',
  'Scadență:': 'Due date:',
  'Stare de plată': 'Payment status',
  'Marchează plătită': 'Mark as paid',
  'Marchează neplătită': 'Mark as unpaid',

  // Mesaje
  'Mesaje & oferte': 'Messages & quotes',
  'Nicio conversație': 'No conversations',
  '← Mesaje': '← Messages',
  'Redeschide conversația': 'Reopen conversation',
  'Închide conversația': 'Close conversation',
  'Răspunde': 'Reply',
  'Scrieți un mesaj…': 'Write a message…',

  // Cereri ofertă
  'Cereri ofertă': 'Quote requests',
  '← Cereri ofertă': '← Quote requests',
  'Cererile de ofertă ale clienților (ciornele nu apar aici).':
    "Customers' quote requests (drafts do not appear here).",
  'Nicio cerere de ofertă': 'No quote requests',
  'Cererile trimise de clienți apar aici.': 'Requests submitted by customers appear here.',
  'Cerere de ofertă': 'Quote request',
  'Kilometraj:': 'Mileage:',
  'Problemă:': 'Problem:',
  'Când apare:': 'When it occurs:',
  'Conducibilă:': 'Drivable:',
  'Martori:': 'Warning lights:',
  'Contact preferat:': 'Preferred contact:',
  'Interval:': 'Time slot:',
  'Răspunsuri trimise': 'Sent replies',
  'Acțiuni': 'Actions',
  'Preia în analiză': 'Take under review',
  'Cere informații': 'Request information',
  'Închide': 'Close',
  'Trimite oferta': 'Send quote',
  'Estimare, piese, manoperă, termen…': 'Estimate, parts, labor, timeline…',
  'Trimite oferta (REPLIED)': 'Send quote (REPLIED)',
  'Tranziție nepermisă.': 'Transition not allowed.',
  'Trimiterea răspunsului a eșuat.': 'Failed to send the reply.',

  // Asistență rutieră
  '← Asistență rutieră': '← Roadside assistance',
  'Nicio cerere de asistență': 'No assistance requests',
  'Locație:': 'Location:',
  'Mobilitate:': 'Mobility:',
  'Siguranță:': 'Safety:',
  'Telefon:': 'Phone:',
  'Direcționează (contact telefonic)': 'Forward (phone contact)',
  'Finalizează': 'Complete',
  'Anulează': 'Cancel',

  // Mobilitate
  'Mobilitate': 'Mobility',
  '← Mobilitate': '← Mobility',
  'Nicio solicitare de mobilitate': 'No mobility requests',
  ' · pentru {date}': ' · for {date}',
  'Detalii:': 'Details:',
  'Data preferată:': 'Preferred date:',
  'Client contactat': 'Customer contacted',
  'Confirmă': 'Confirm',

  // Daune
  'Dosare de daună': 'Damage claims',
  '← Dosare de daună': '← Damage claims',
  'Niciun dosar de daună': 'No damage claims',
  'Dosar de daună': 'Damage claim',
  'Cere documente': 'Request documents',
  'Data:': 'Date:',
  'Loc:': 'Location:',
  'Descriere:': 'Description:',
  'Poliță:': 'Policy:',
  'Fotografii și documente:': 'Photos and documents:',

  // Securitate / 2FA (P0-06)
  '🔐 Securitate': '🔐 Security',
  '← Înapoi': '← Back',
  'Securitatea contului': 'Account security',
  'Autentificare în doi pași (2FA)': 'Two-factor authentication (2FA)',
  'Nu am putut încărca datele contului.': 'Could not load account details.',
  'Verificare în doi pași': 'Two-step verification',
  'Introduceți codul din aplicația de autentificare (Google/Microsoft Authenticator).': 'Enter the code from your authenticator app (Google/Microsoft Authenticator).',
  'Introduceți unul dintre codurile de rezervă primite la activarea 2FA.': 'Enter one of the recovery codes you received when enabling 2FA.',
  'Cod din aplicație (6 cifre)': 'Code from the app (6 digits)',
  'Cod de rezervă': 'Recovery code',
  'Se verifică…': 'Verifying…',
  'Verifică': 'Verify',
  'Folosesc codul din aplicație': 'Use the app code instead',
  'Nu am telefonul — folosesc un cod de rezervă': "I don't have my phone — use a recovery code",
  '2FA este ACTIV pe acest cont — la fiecare login se cere codul din aplicația de autentificare.': '2FA is ACTIVE on this account — every login requires the code from your authenticator app.',
  'Se dezactivează…': 'Disabling…',
  'Dezactivează 2FA': 'Disable 2FA',
  'Protejați contul de administrator cu un al doilea factor: un cod generat de aplicația de autentificare (Google/Microsoft Authenticator, Authy).': 'Protect the administrator account with a second factor: a code generated by your authenticator app (Google/Microsoft Authenticator, Authy).',
  'Confirmați parola contului': 'Confirm your account password',
  'Se pregătește…': 'Preparing…',
  'Activează 2FA': 'Enable 2FA',
  'Pasul 2: adăugați contul în aplicația de autentificare': 'Step 2: add the account to your authenticator app',
  'Scanați codul QR din aplicație folosind linkul de mai jos sau introduceți secretul manual, apoi confirmați cu primul cod generat.': 'Scan the link below with the app or enter the secret manually, then confirm with the first generated code.',
  'Secret (introducere manuală)': 'Secret (manual entry)',
  'Link de configurare (otpauth)': 'Setup link (otpauth)',
  'Se confirmă…': 'Confirming…',
  'Confirmă și activează': 'Confirm and enable',
  '2FA activat': '2FA enabled',
  'Salvați codurile de rezervă de mai jos într-un loc sigur — se afișează O SINGURĂ DATĂ. Fiecare cod funcționează o singură dată, dacă pierdeți accesul la aplicația de autentificare.': 'Store the recovery codes below somewhere safe — they are shown ONLY ONCE. Each code works a single time if you lose access to your authenticator app.',
  'Am salvat codurile de rezervă': 'I have saved the recovery codes',
  // Mesaje de eroare de la backend (2FA)
  'Parola introdusă nu este corectă.': 'The password you entered is not correct.',
  'Cod TOTP invalid sau expirat.': 'Invalid or expired TOTP code.',
  'Cod TOTP sau cod de rezervă invalid.': 'Invalid TOTP or recovery code.',
  'Este necesară verificarea în doi factori.': 'Two-factor verification is required.',
  'Contul de service trebuie să activeze 2FA înainte de a folosi portalul.': 'The service account must enable 2FA before using the portal.',
  '2FA este deja activ pe acest cont.': '2FA is already active on this account.',
  '2FA nu este activat pe acest cont.': '2FA is not enabled on this account.',

  // Liste mari (P2-03)
  'Afișează încă {n} (din {m} rămase)': 'Show {n} more ({m} remaining)',

  // ASM (.xls) import
  'Sărite — VIN nevalid (rămân doar în ASM):': 'Skipped — invalid VIN (they stay in ASM only):',
  'Parteneri fără vehicul:': 'Partners without a vehicle:',
  'Încărcați „lista parteneri" din ASM (.xls, .xlsx sau .csv). Coloane recunoscute:': 'Upload the ASM “lista parteneri” export (.xls, .xlsx or .csv). Recognised columns:',
  'sau': 'or',
  'Fișier (.xls, .xlsx sau .csv, max. 10 MB)': 'File (.xls, .xlsx or .csv, max. 10 MB)',
  'Fișier istoric (.xls, .xlsx sau .csv, max. 10 MB)': 'History file (.xls, .xlsx or .csv, max. 10 MB)',
  'Pasul 2 — Istoric reparații → carte service': 'Step 2 — Repair list → service book',
  'Exportul „PersonalManopere" din ASM sau orice tabel legat prin VIN (importați întâi clienții). Coloane recunoscute:': 'The ASM “PersonalManopere” export, or any table linked by VIN (import the clients first). Recognised columns:',
  'Pasul 3 — Report ITP/RCA → scadențe': 'Step 3 — ITP/RCA report → deadlines',
  'Raportul de alerte din ASM („report itp . rca"). Coloane recunoscute:': 'The ASM alert report (“report itp . rca”). Recognised columns:',
  'Doar alertele ITP/RCA devin scadențe (restul se sar); vehiculul se identifică după număr, iar reimportul actualizează fără dubluri. Scadențele apar apoi în Alerte (client) și 🔔 Notificări.': 'Only ITP/RCA alerts become deadlines (the rest are skipped); the vehicle is matched by plate, and re-importing updates without duplicates. Deadlines then appear in Alerts (client) and 🔔 Notifications.',
  'Fișier raport (.xls, .xlsx sau .csv, max. 10 MB)': 'Report file (.xls, .xlsx or .csv, max. 10 MB)',
  'Importă scadențele': 'Import deadlines',
  'Raport scadențe': 'Deadline report',
  'Scadențe create:': 'Deadlines created:',
  'Neschimbate (sărite):': 'Unchanged (skipped):',
  'alte alerte (nu ITP/RCA):': 'other alerts (not ITP/RCA):',

  // Notificări de scadență (P1-03)
  '🔔 Notificări': '🔔 Notifications',
  'Notificări de scadență': 'Deadline notifications',
  'Mesajul se deschide gata scris în WhatsApp-ul service-ului sau în email — îl trimiteți cu un click, iar trimiterea se consemnează.':
    'The message opens pre-written in the workshop’s own WhatsApp or in email — send it with one click, and the send is recorded.',
  'Fereastra de timp': 'Time window',
  'Următoarele {n} zile': 'Next {n} days',
  'Nicio scadență în fereastra aleasă': 'No deadlines in the selected window',
  'în {n} zile': 'in {n} days',
  'expirat': 'expired',
  'fără telefon': 'no phone number',
  'Notificat ultima dată: {date}': 'Last notified: {date}',
  '📲 WhatsApp': '📲 WhatsApp',
  '✉️ Email': '✉️ Email',
};

const DICTS: Record<Locale, Dict | null> = { ro: null, hu: HU, en: EN };

/** Înregistrează traduceri suplimentare (apelat din modulele de pagină). */
export function extendDictionary(locale: Exclude<Locale, 'ro'>, entries: Dict): void {
  Object.assign(DICTS[locale] as Dict, entries);
}

interface LocaleCtx {
  locale: Locale;
  setLocale: (l: Locale) => void;
}

const Ctx = createContext<LocaleCtx>({ locale: 'ro', setLocale: () => undefined });

export function LocaleProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>('ro');

  useEffect(() => {
    const saved = window.localStorage.getItem(STORAGE_KEY);
    if (saved === 'hu' || saved === 'en' || saved === 'ro') setLocaleState(saved);
  }, []);

  const setLocale = useCallback((l: Locale) => {
    setLocaleState(l);
    window.localStorage.setItem(STORAGE_KEY, l);
    document.documentElement.lang = l;
  }, []);

  return <Ctx.Provider value={{ locale, setLocale }}>{children}</Ctx.Provider>;
}

export function useLocale(): LocaleCtx {
  return useContext(Ctx);
}

/**
 * Traducătorul: t('text românesc') → textul în limba aleasă.
 * Variabile: t('Rândul {n}', { n: 4 }).
 */
export function useT(): (text: string, vars?: Record<string, string | number>) => string {
  const { locale } = useContext(Ctx);

  return useCallback(
    (text: string, vars?: Record<string, string | number>) => {
      const dict = DICTS[locale];
      let out = dict?.[text] ?? text;
      if (vars) {
        for (const [k, v] of Object.entries(vars)) {
          out = out.replaceAll(`{${k}}`, String(v));
        }
      }
      return out;
    },
    [locale],
  );
}

/** Comutatorul de limbă: RO · HU · EN (româna este implicită). */
export function LangSwitcher() {
  const { locale, setLocale } = useLocale();
  return (
    <div className="lang-switcher" role="group" aria-label="Limbă / Nyelv / Language">
      {(Object.keys(LOCALE_NAMES) as Locale[]).map((l) => (
        <button
          key={l}
          type="button"
          className={l === locale ? 'active' : ''}
          aria-pressed={l === locale}
          onClick={() => setLocale(l)}
        >
          {LOCALE_NAMES[l]}
        </button>
      ))}
    </div>
  );
}
