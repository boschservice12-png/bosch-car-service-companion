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
  // Navigație / comune
  'Acasă': 'Kezdőlap',
  'Vehicule': 'Járművek',
  'Mesaje': 'Üzenetek',
  'Profil': 'Profil',
  'Navigație principală': 'Fő navigáció',
  'Se încarcă…': 'Betöltés…',
  'Se încarcă': 'Betöltés',
  'A apărut o eroare.': 'Hiba történt.',
  'A apărut o eroare. Încercați din nou.': 'Hiba történt. Próbálja újra.',
  'Reîncearcă': 'Újra',
  'Eroare la încărcare.': 'Betöltési hiba.',
  'Conexiune indisponibilă. Verificați internetul.': 'Nincs kapcsolat. Ellenőrizze az internetet.',
  'Trimitere eșuată.': 'A küldés nem sikerült.',
  'Operațiune eșuată.': 'A művelet nem sikerült.',
  'Salvare eșuată.': 'A mentés nem sikerült.',
  'Ștergere eșuată.': 'A törlés nem sikerült.',
  'Încărcare eșuată.': 'A feltöltés nem sikerült.',
  'Anulare eșuată.': 'A visszavonás nem sikerült.',
  'Descărcare eșuată.': 'A letöltés nem sikerült.',
  'Renunță': 'Mégse',
  'Salvează': 'Mentés',
  'Se salvează…': 'Mentés…',
  'Se trimite…': 'Küldés…',
  'Trimite': 'Küldés',
  'Adaugă': 'Hozzáadás',
  'Ieșire': 'Kijelentkezés',
  'opțional': 'opcionális',
  'Descarcă': 'Letöltés',
  'document': 'dokumentum',
  'indisponibil': 'nem elérhető',
  'în curs de scanare': 'vizsgálat folyamatban',
  'Notă service:': 'Szerviz megjegyzése:',
  'Vehicul:': 'Jármű:',
  'Vehicul (opțional)': 'Jármű (opcionális)',
  '— fără vehicul —': '— jármű nélkül —',
  'Tip': 'Típus',
  'An': 'Év',
  'Da': 'Igen',
  'Nu': 'Nem',
  'Documente:': 'Dokumentumok:',
  'Email': 'E-mail',
  'Parolă': 'Jelszó',
  '+ Cerere': '+ Kérelem',
  'Cererea nu este disponibilă.': 'A kérelem nem érhető el.',

  // Acasă
  'Bună,': 'Üdv,',
  'client': 'ügyfél',
  'Bine ați venit la Bosch Car Service Companion.': 'Üdvözöljük a Bosch Car Service Companion alkalmazásban.',
  'Scurtături': 'Gyorsgombok',
  'Servicii': 'Szolgáltatások',
  '🚗 Vehiculele mele': '🚗 Járműveim',
  '➕ Adaugă un vehicul': '➕ Jármű hozzáadása',
  '🔔 Alerte (ITP · RCA · taxă de drum · asistență)': '🔔 Riasztások (műszaki · RCA · útdíj · assistance)',
  '🧰 Cere ofertă': '🧰 Ajánlatkérés',
  '💬 Mesaje': '💬 Üzenetek',
  '🆘 Asistență rutieră': '🆘 Autómentés',
  '🚕 Mobilitate': '🚕 Mobilitás',
  '📋 În caz de accident ↗': '📋 Baleset esetén ↗',
  '🧾 Taxe și impozite': '🧾 Adók és illetékek',

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
  'JPG, PNG, WEBP sau PDF · max {n} MB': 'JPG, PNG, WEBP vagy PDF · max. {n} MB',

  // Istoric service (ServiceRecordView)
  'Lucrare': 'Munkálat',
  '✎ Corecție': '✎ Javítás',
  'Motiv:': 'Ok:',
  '• Corectat ulterior': '• Utólag javítva',
  'Piese:': 'Alkatrészek:',
  'Manoperă:': 'Munkadíj:',
  'Total:': 'Összesen:',
  'Garanție:': 'Garancia:',

  // Autentificare
  'Autentificare': 'Bejelentkezés',
  'Se conectează…': 'Bejelentkezés…',
  'Intră în cont': 'Belépés',
  'Nu aveți cont?': 'Nincs még fiókja?',
  'Creați unul': 'Hozzon létre egyet',

  // Creare cont
  'Creare cont': 'Fiók létrehozása',
  'Prenume': 'Keresztnév',
  'Nume': 'Vezetéknév',
  'Minim 8 caractere.': 'Legalább 8 karakter.',
  'Sunt de acord cu prelucrarea datelor mele personale conform informării de confidențialitate.':
    'Hozzájárulok személyes adataim kezeléséhez az adatvédelmi tájékoztatóban foglaltak szerint.',
  'Se creează…': 'Létrehozás…',
  'Creează cont': 'Fiók létrehozása',
  'Aveți deja cont?': 'Már van fiókja?',
  'Autentificați-vă': 'Jelentkezzen be',

  // Profil
  'Nume:': 'Név:',
  'Email:': 'E-mail:',
  'Rol:': 'Szerep:',
  'Administrator service': 'Szerviz-adminisztrátor',
  'Deconectare': 'Kijelentkezés',

  // Alerte
  '← Acasă': '← Kezdőlap',
  'Alerte': 'Riasztások',
  'ITP · RCA · Taxă de drum · Asistență rutieră — pentru toate vehiculele dumneavoastră.':
    'Műszaki vizsga · RCA · Útdíj · Autómentés — az összes járművéhez.',
  'Nicio alertă': 'Nincs riasztás',
  'Adăugați scadențele (ITP, RCA, taxă de drum) pe pagina fiecărui vehicul.':
    'Adja meg a lejáratokat (műszaki, RCA, útdíj) az egyes járművek oldalán.',
  ' · verificare ITP (RAR) ↗': ' · műszaki vizsga ellenőrzése (RAR) ↗',
  ' · verificare RCA (AIDA) ↗': ' · RCA ellenőrzése (AIDA) ↗',
  ' · verificare taxă de drum (eRovinieta) ↗': ' · útdíj ellenőrzése (eRovinieta) ↗',
  ' · validat de service': ' · szerviz által jóváhagyva',
  '🚗 Gestionează scadențele pe vehicul': '🚗 Lejáratok kezelése járművenként',
  'Stările se calculează pe baza datelor introduse și validate; aplicația nu interoghează baze oficiale.':
    'Az állapotokat a megadott és jóváhagyott adatok alapján számítjuk; az alkalmazás nem kérdez le hivatalos adatbázisokat.',

  // Vehicule
  'Vehiculele mele': 'Járműveim',
  'Nu am putut încărca vehiculele.': 'Nem sikerült betölteni a járműveket.',
  'Niciun vehicul încă': 'Még nincs jármű',
  'Adăugați primul vehicul pentru a urmări scadențele și istoricul.':
    'Adja hozzá első járművét a lejáratok és az előzmények követéséhez.',
  '➕ Adaugă vehicul': '➕ Jármű hozzáadása',
  'Detalii {plate}': '{plate} részletei',
  'Detalii →': 'Részletek →',
  '➕ Adaugă alt vehicul': '➕ Másik jármű hozzáadása',
  'Adaugă vehicul': 'Jármű hozzáadása',
  'Serie de șasiu (VIN)': 'Alvázszám (VIN)',
  '17 caractere, fără literele I, O, Q.': '17 karakter, az I, O, Q betűk nélkül.',
  'Număr de înmatriculare': 'Rendszám',
  'Marcă': 'Márka',
  'Model': 'Modell',
  'An fabricație': 'Gyártási év',
  'Salvează vehiculul': 'Jármű mentése',
  'Vehiculul nu a fost găsit sau nu vă aparține.': 'A jármű nem található, vagy nem Önhöz tartozik.',
  'Scadențe': 'Lejáratok',
  'neintrodus': 'nincs megadva',
  ' · validat': ' · jóváhagyva',
  'Verificare ITP (RAR) ↗': 'Műszaki vizsga ellenőrzése (RAR) ↗',
  'Verificare RCA (AIDA) ↗': 'RCA ellenőrzése (AIDA) ↗',
  'Verificare taxă de drum (eRovinieta) ↗': 'Útdíj ellenőrzése (eRovinieta) ↗',
  '➕ Adaugă / actualizează scadență': '➕ Lejárat hozzáadása / frissítése',
  '🧾 Istoric service': '🧾 Szervizelőzmények',
  '← Vehicul': '← Jármű',
  'Istoric service': 'Szervizelőzmények',
  'Istoricul nu este disponibil sau vehiculul nu vă aparține.':
    'Az előzmények nem érhetők el, vagy a jármű nem Önhöz tartozik.',
  'Nicio intrare în istoric': 'Nincs bejegyzés az előzményekben',
  'Service-ul va publica aici lucrările efectuate.': 'A szerviz itt teszi közzé az elvégzett munkálatokat.',
  'Istoricul este publicat de service. Corecțiile apar ca intrări separate, păstrând înregistrarea originală.':
    'Az előzményeket a szerviz teszi közzé. A javítások külön bejegyzésként jelennek meg, az eredeti bejegyzés megőrzésével.',
  'Adaugă scadență': 'Lejárat hozzáadása',
  'Data expirării': 'Lejárat dátuma',
  'Valabil de la (opțional)': 'Érvényes ettől (opcionális)',
  'Notă (opțional)': 'Megjegyzés (opcionális)',
  'Salvează scadența': 'Lejárat mentése',

  // Taxe și impozite
  'Taxe și impozite': 'Adók és illetékek',
  '+ Taxă': '+ Adó',
  'Nicio taxă': 'Nincs adó',
  'Adăugați taxele și impozitele anuale pentru a le urmări plata.':
    'Adja hozzá az éves adókat és illetékeket a befizetések követéséhez.',
  ' · scadent {date}': ' · esedékes: {date}',
  '← Taxe și impozite': '← Adók és illetékek',
  'Taxa nu este disponibilă.': 'Az adó nem érhető el.',
  'Plătit până acum:': 'Eddig befizetve:',
  'Scadență:': 'Esedékesség:',
  'Plătită la:': 'Befizetve:',
  'Taxa este plătită integral. Pentru corecții contactați service-ul.':
    'Az adó teljes egészében be van fizetve. Javításért forduljon a szervizhez.',
  'Editează taxa': 'Adó szerkesztése',
  'Sumă (RON)': 'Összeg (RON)',
  'Scadență (opțional)': 'Esedékesség (opcionális)',
  'Marchează plata': 'Befizetés rögzítése',
  'Evidență declarativă — nu se încarcă niciun document. Goliți suma pentru plată integrală.':
    'Nyilvántartás saját bevallás alapján — dokumentumot nem kell feltölteni. Teljes befizetéshez hagyja üresen az összeget.',
  'Sumă plătită (RON, opțional — pentru plată parțială)':
    'Befizetett összeg (RON, opcionális — részleges befizetéshez)',
  'Înregistrează plata parțială': 'Részleges befizetés rögzítése',
  'Marchează plătită integral': 'Megjelölés teljesen befizetettként',
  '✏️ Editează': '✏️ Szerkesztés',
  '🗑 Șterge': '🗑 Törlés',
  'Ștergeți această taxă din evidență?': 'Törli ezt az adót a nyilvántartásból?',
  'Taxă nouă': 'Új adó',
  'Adaugă taxa': 'Adó hozzáadása',

  // Asistență rutieră
  'Solicită asistență — alege situația ta': 'Kérjen segítséget — válassza ki a helyzetét',
  '🚨 Am asistență rutieră activă': '🚨 Van aktív autómentés-szolgáltatásom',
  'Fără asistență rutieră': 'Nincs autómentés-szolgáltatásom',
  'Linia de asistență NON-STOP': 'NON-STOP segélyvonal',
  'Dispeceratul service-ului': 'A szerviz diszpécserszolgálata',
  'NON-STOP, 24/7 — pentru clienții cu asistență rutieră activă.':
    'NON-STOP, 24/7 — aktív autómentés-szolgáltatással rendelkező ügyfeleknek.',
  'Intervenția se facturează separat.': 'A beavatkozást külön számlázzuk.',
  'Nicio cerere': 'Nincs kérelem',
  'Deschideți o cerere de asistență dacă aveți nevoie de ajutor pe drum.':
    'Nyisson segítségkérelmet, ha útközben segítségre van szüksége.',
  '← Asistență rutieră': '← Autómentés',
  'Cerere de asistență': 'Segítségkérelem',
  'Locație:': 'Helyszín:',
  'Problemă:': 'Probléma:',
  'Mobilitate:': 'Mozgásképesség:',
  'Siguranță:': 'Biztonság:',
  'Telefon:': 'Telefon:',
  'Anulează cererea': 'Kérelem visszavonása',
  'Locația': 'Helyszín',
  'Ex: DN13, km 12': 'Pl.: DN13, 12-es km',
  'Problema': 'A probléma',
  'Mașina se poate deplasa?': 'A jármű mozgásképes?',
  'Da, se poate deplasa': 'Igen, mozgásképes',
  'Nu, este imobilizată': 'Nem, mozgásképtelen',
  'Sunteți în siguranță?': 'Biztonságban van?',
  'Da, în siguranță': 'Igen, biztonságban vagyok',
  'Nu, situație periculoasă': 'Nem, veszélyes a helyzet',
  'Telefon de contact': 'Kapcsolattartási telefonszám',
  'Trimite cererea': 'Kérelem elküldése',

  // Mobilitate
  'Mobilitate': 'Mobilitás',
  'Aveți nevoie de mobilitate acum?': 'Mobilitásra van szüksége most?',
  'Trimiteți o cerere, sau sunați direct dispeceratul.': 'Küldjön kérelmet, vagy hívja közvetlenül a diszpécserszolgálatot.',
  '📞 Sună {phone}': '📞 Hívás: {phone}',
  'Nicio solicitare': 'Nincs igénylés',
  'Cereți o mașină de înlocuire, un taxi sau transport acasă.': 'Kérjen cserejárművet, taxit vagy hazaszállítást.',
  'Pentru {date}': '{date} napra',
  '← Mobilitate': '← Mobilitás',
  'Solicitarea nu este disponibilă.': 'Az igénylés nem érhető el.',
  'Detalii:': 'Részletek:',
  'Data preferată:': 'Kívánt dátum:',
  'Anulează solicitarea': 'Igénylés visszavonása',
  'Solicitare de mobilitate': 'Mobilitási igénylés',
  'Detalii': 'Részletek',
  'Data preferată (opțional)': 'Kívánt dátum (opcionális)',
  'Trimite solicitarea': 'Igénylés elküldése',

  // Mesaje
  '+ Nou': '+ Új',
  'Nicio conversație': 'Nincs beszélgetés',
  'Trimiteți un mesaj sau o cerere de ofertă service-ului.': 'Küldjön üzenetet vagy ajánlatkérést a szerviznek.',
  '← Mesaje': '← Üzenetek',
  'Conversația nu este disponibilă.': 'A beszélgetés nem érhető el.',
  'Conversație închisă de service. Nu se mai pot trimite mesaje.':
    'A beszélgetést a szerviz lezárta. Több üzenet nem küldhető.',
  'Răspunde': 'Válasz',
  'Scrieți un mesaj…': 'Írjon üzenetet…',
  'Mesaj nou': 'Új üzenet',
  'Subiect': 'Tárgy',
  'Mesaj': 'Üzenet',

  // Oferte
  'Cere ofertă': 'Ajánlatkérés',
  'Nicio cerere de ofertă': 'Nincs ajánlatkérés',
  'Descrieți problema și primiți o estimare de preț de la service.':
    'Írja le a problémát, és kapjon árbecslést a szerviztől.',
  '← Cereri de ofertă': '← Ajánlatkérések',
  'Cerere de ofertă': 'Ajánlatkérés',
  'Kilometraj:': 'Kilométeróra-állás:',
  'Când apare:': 'Mikor jelentkezik:',
  'Conducibilă:': 'Vezethető:',
  'Martori:': 'Visszajelző lámpák:',
  'Interval preferat:': 'Kívánt időszak:',
  'Răspunsul service-ului': 'A szerviz válasza',
  'Trimite cererea către service': 'Kérelem elküldése a szerviznek',
  'Service-ul are nevoie de informații suplimentare. Contactați service-ul (telefon sau mesaj), apoi confirmați:':
    'A szerviznek további információkra van szüksége. Vegye fel a kapcsolatot a szervizzel (telefonon vagy üzenetben), majd erősítse meg:',
  'Am completat informațiile': 'Kiegészítettem az információkat',
  'Acceptă oferta': 'Ajánlat elfogadása',
  'Refuză': 'Elutasítás',
  'Cere ofertă de preț': 'Kérjen árajánlatot',
  'Descrieți problema — service-ul revine cu o estimare. Fără obligații.':
    'Írja le a problémát — a szerviz árbecsléssel jelentkezik. Kötelezettségek nélkül.',
  'Kilometraj (opțional)': 'Kilométeróra-állás (opcionális)',
  'Descrierea simptomului *': 'A tünet leírása *',
  'Ex.: zgomot metalic la frânare, în față…': 'Pl.: fémes zaj fékezéskor, elöl…',
  'Când apare (opțional)': 'Mikor jelentkezik (opcionális)',
  'Ex.: la frânări puternice': 'Pl.: erős fékezéskor',
  'Mașina e conducibilă?': 'A jármű vezethető?',
  'Martori aprinși în bord (opțional)': 'Világító műszerfali visszajelzők (opcionális)',
  'Ex.: ABS, motor': 'Pl.: ABS, motor',
  'Contact preferat': 'Kívánt kapcsolattartási mód',
  'Telefon': 'Telefon',
  'Mesaj în aplicație': 'Üzenet az alkalmazásban',
  'Interval preferat (opțional)': 'Kívánt időszak (opcionális)',
  'Ex.: luni–vineri după 16:00': 'Pl.: hétfő–péntek 16:00 után',
  'Salvează ca ciornă': 'Mentés piszkozatként',

  // Înregistrare — revendicarea contului importat (P1-02)
  'Număr de înmatriculare (opțional)': 'Rendszám (nem kötelező)',
  'Dacă sunteți deja client al service-ului, confirmați numărul de înmatriculare al mașinii — contul se leagă automat de vehiculele și istoricul dumneavoastră.':
    'Ha már a szerviz ügyfele, erősítse meg az autó rendszámát — a fiók automatikusan összekapcsolódik a járműveivel és a szerviztörténetével.',
  'Acest email este deja în evidența service-ului. Introduceți numărul de înmatriculare al mașinii pentru a activa contul.':
    'Ez az e-mail már szerepel a szerviz nyilvántartásában. A fiók aktiválásához adja meg az autó rendszámát.',
  'Numărul de înmatriculare nu corespunde evidenței service-ului.':
    'A rendszám nem egyezik a szerviz nyilvántartásával.',

  // PWA telepítés (P2-01)
  'Instalează Companion pe telefon': 'Telepítse a Companiont a telefonra',
  'Accesează rapid cartea de service, documentele și mesajele service-ului.': 'Gyors hozzáférés a szervizkönyvhöz, a dokumentumokhoz és a szerviz üzeneteihez.',
  'Instalează': 'Telepítés',
  'Închide': 'Bezárás',
  'Instalează Companion pe ecranul telefonului': 'Tegye a Companiont a telefon főképernyőjére',
  'Apasă butonul Partajare din Safari.': 'Nyomja meg a Megosztás gombot a Safariban.',
  'Selectează „Adăugați la ecranul principal”.': 'Válassza a „Hozzáadás a főképernyőhöz” lehetőséget.',
  'Confirmă apăsând „Adăugați”.': 'Erősítse meg a „Hozzáadás” gombbal.',
  // GDPR (P1-06)
  'Datele mele (GDPR)': 'Az adataim (GDPR)',
  '⬇️ Descarcă datele mele (JSON)': '⬇️ Adataim letöltése (JSON)',
  'Șterge contul…': 'Fiók törlése…',
  'Contul se blochează imediat, iar după 30 de zile datele personale se șterg definitiv. În acest interval vă puteți răzgândi contactând service-ul.':
    'A fiók azonnal zárolódik, 30 nap után pedig a személyes adatok véglegesen törlődnek. Ez idő alatt meggondolhatja magát — vegye fel a kapcsolatot a szervizzel.',
  'Confirmați parola': 'Erősítse meg a jelszavát',
  'Confirm ștergerea contului': 'Megerősítem a fiók törlését',
};

const EN: Dict = {
  // Navigație / comune
  'Acasă': 'Home',
  'Vehicule': 'Vehicles',
  'Mesaje': 'Messages',
  'Profil': 'Profile',
  'Navigație principală': 'Main navigation',
  'Se încarcă…': 'Loading…',
  'Se încarcă': 'Loading',
  'A apărut o eroare.': 'Something went wrong.',
  'A apărut o eroare. Încercați din nou.': 'Something went wrong. Please try again.',
  'Reîncearcă': 'Retry',
  'Eroare la încărcare.': 'Failed to load.',
  'Conexiune indisponibilă. Verificați internetul.': 'No connection. Check your internet.',
  'Trimitere eșuată.': 'Failed to send.',
  'Operațiune eșuată.': 'Operation failed.',
  'Salvare eșuată.': 'Failed to save.',
  'Ștergere eșuată.': 'Failed to delete.',
  'Încărcare eșuată.': 'Upload failed.',
  'Anulare eșuată.': 'Failed to cancel.',
  'Descărcare eșuată.': 'Download failed.',
  'Renunță': 'Cancel',
  'Salvează': 'Save',
  'Se salvează…': 'Saving…',
  'Se trimite…': 'Sending…',
  'Trimite': 'Send',
  'Adaugă': 'Add',
  'Ieșire': 'Sign out',
  'opțional': 'optional',
  'Descarcă': 'Download',
  'document': 'document',
  'indisponibil': 'unavailable',
  'în curs de scanare': 'scan in progress',
  'Notă service:': 'Service note:',
  'Vehicul:': 'Vehicle:',
  'Vehicul (opțional)': 'Vehicle (optional)',
  '— fără vehicul —': '— no vehicle —',
  'Tip': 'Type',
  'An': 'Year',
  'Da': 'Yes',
  'Nu': 'No',
  'Documente:': 'Documents:',
  'Email': 'Email',
  'Parolă': 'Password',
  '+ Cerere': '+ Request',
  'Cererea nu este disponibilă.': 'This request is not available.',

  // Acasă
  'Bună,': 'Hello,',
  'client': 'customer',
  'Bine ați venit la Bosch Car Service Companion.': 'Welcome to Bosch Car Service Companion.',
  'Scurtături': 'Shortcuts',
  'Servicii': 'Services',
  '🚗 Vehiculele mele': '🚗 My vehicles',
  '➕ Adaugă un vehicul': '➕ Add a vehicle',
  '🔔 Alerte (ITP · RCA · taxă de drum · asistență)': '🔔 Alerts (inspection · RCA · road tax · assistance)',
  '🧰 Cere ofertă': '🧰 Request a quote',
  '💬 Mesaje': '💬 Messages',
  '🆘 Asistență rutieră': '🆘 Roadside assistance',
  '🚕 Mobilitate': '🚕 Mobility',
  '📋 În caz de accident ↗': '📋 In case of accident ↗',
  '🧾 Taxe și impozite': '🧾 Taxes & duties',

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
  'JPG, PNG, WEBP sau PDF · max {n} MB': 'JPG, PNG, WEBP or PDF · max {n} MB',

  // Istoric service (ServiceRecordView)
  'Lucrare': 'Work',
  '✎ Corecție': '✎ Correction',
  'Motiv:': 'Reason:',
  '• Corectat ulterior': '• Corrected later',
  'Piese:': 'Parts:',
  'Manoperă:': 'Labor:',
  'Total:': 'Total:',
  'Garanție:': 'Warranty:',

  // Autentificare
  'Autentificare': 'Sign in',
  'Se conectează…': 'Signing in…',
  'Intră în cont': 'Sign in',
  'Nu aveți cont?': "Don't have an account?",
  'Creați unul': 'Create one',

  // Creare cont
  'Creare cont': 'Create account',
  'Prenume': 'First name',
  'Nume': 'Last name',
  'Minim 8 caractere.': 'At least 8 characters.',
  'Sunt de acord cu prelucrarea datelor mele personale conform informării de confidențialitate.':
    'I agree to the processing of my personal data as described in the privacy notice.',
  'Se creează…': 'Creating…',
  'Creează cont': 'Create account',
  'Aveți deja cont?': 'Already have an account?',
  'Autentificați-vă': 'Sign in',

  // Profil
  'Nume:': 'Name:',
  'Email:': 'Email:',
  'Rol:': 'Role:',
  'Administrator service': 'Service administrator',
  'Deconectare': 'Sign out',

  // Alerte
  '← Acasă': '← Home',
  'Alerte': 'Alerts',
  'ITP · RCA · Taxă de drum · Asistență rutieră — pentru toate vehiculele dumneavoastră.':
    'Inspection · RCA · Road tax · Roadside assistance — for all your vehicles.',
  'Nicio alertă': 'No alerts',
  'Adăugați scadențele (ITP, RCA, taxă de drum) pe pagina fiecărui vehicul.':
    'Add deadlines (inspection, RCA, road tax) on each vehicle page.',
  ' · verificare ITP (RAR) ↗': ' · check inspection (RAR) ↗',
  ' · verificare RCA (AIDA) ↗': ' · check RCA (AIDA) ↗',
  ' · verificare taxă de drum (eRovinieta) ↗': ' · check road tax (eRovinieta) ↗',
  ' · validat de service': ' · verified by the service',
  '🚗 Gestionează scadențele pe vehicul': '🚗 Manage deadlines per vehicle',
  'Stările se calculează pe baza datelor introduse și validate; aplicația nu interoghează baze oficiale.':
    'Statuses are calculated from entered and verified data; the app does not query official databases.',

  // Vehicule
  'Vehiculele mele': 'My vehicles',
  'Nu am putut încărca vehiculele.': 'Could not load vehicles.',
  'Niciun vehicul încă': 'No vehicles yet',
  'Adăugați primul vehicul pentru a urmări scadențele și istoricul.':
    'Add your first vehicle to track deadlines and history.',
  '➕ Adaugă vehicul': '➕ Add vehicle',
  'Detalii {plate}': 'Details for {plate}',
  'Detalii →': 'Details →',
  '➕ Adaugă alt vehicul': '➕ Add another vehicle',
  'Adaugă vehicul': 'Add vehicle',
  'Serie de șasiu (VIN)': 'Chassis number (VIN)',
  '17 caractere, fără literele I, O, Q.': '17 characters, without the letters I, O, Q.',
  'Număr de înmatriculare': 'License plate',
  'Marcă': 'Make',
  'Model': 'Model',
  'An fabricație': 'Year of manufacture',
  'Salvează vehiculul': 'Save vehicle',
  'Vehiculul nu a fost găsit sau nu vă aparține.': 'Vehicle not found or does not belong to you.',
  'Scadențe': 'Deadlines',
  'neintrodus': 'not set',
  ' · validat': ' · verified',
  'Verificare ITP (RAR) ↗': 'Check inspection (RAR) ↗',
  'Verificare RCA (AIDA) ↗': 'Check RCA (AIDA) ↗',
  'Verificare taxă de drum (eRovinieta) ↗': 'Check road tax (eRovinieta) ↗',
  '➕ Adaugă / actualizează scadență': '➕ Add / update deadline',
  '🧾 Istoric service': '🧾 Service history',
  '← Vehicul': '← Vehicle',
  'Istoric service': 'Service history',
  'Istoricul nu este disponibil sau vehiculul nu vă aparține.':
    'History is not available or the vehicle does not belong to you.',
  'Nicio intrare în istoric': 'No service history yet',
  'Service-ul va publica aici lucrările efectuate.': 'The service will publish completed work here.',
  'Istoricul este publicat de service. Corecțiile apar ca intrări separate, păstrând înregistrarea originală.':
    'History is published by the service. Corrections appear as separate entries, preserving the original record.',
  'Adaugă scadență': 'Add deadline',
  'Data expirării': 'Expiry date',
  'Valabil de la (opțional)': 'Valid from (optional)',
  'Notă (opțional)': 'Note (optional)',
  'Salvează scadența': 'Save deadline',

  // Taxe și impozite
  'Taxe și impozite': 'Taxes & duties',
  '+ Taxă': '+ Tax',
  'Nicio taxă': 'No taxes',
  'Adăugați taxele și impozitele anuale pentru a le urmări plata.':
    'Add your annual taxes and duties to track their payment.',
  ' · scadent {date}': ' · due {date}',
  '← Taxe și impozite': '← Taxes & duties',
  'Taxa nu este disponibilă.': 'This tax is not available.',
  'Plătit până acum:': 'Paid so far:',
  'Scadență:': 'Due date:',
  'Plătită la:': 'Paid on:',
  'Taxa este plătită integral. Pentru corecții contactați service-ul.':
    'This tax is fully paid. Contact the service for corrections.',
  'Editează taxa': 'Edit tax',
  'Sumă (RON)': 'Amount (RON)',
  'Scadență (opțional)': 'Due date (optional)',
  'Marchează plata': 'Record payment',
  'Evidență declarativă — nu se încarcă niciun document. Goliți suma pentru plată integrală.':
    'Self-declared record — no document is uploaded. Leave the amount empty for full payment.',
  'Sumă plătită (RON, opțional — pentru plată parțială)': 'Amount paid (RON, optional — for partial payment)',
  'Înregistrează plata parțială': 'Record partial payment',
  'Marchează plătită integral': 'Mark as fully paid',
  '✏️ Editează': '✏️ Edit',
  '🗑 Șterge': '🗑 Delete',
  'Ștergeți această taxă din evidență?': 'Delete this tax from your records?',
  'Taxă nouă': 'New tax',
  'Adaugă taxa': 'Add tax',

  // Asistență rutieră
  'Solicită asistență — alege situația ta': 'Request assistance — choose your situation',
  '🚨 Am asistență rutieră activă': '🚨 I have active roadside assistance',
  'Fără asistență rutieră': 'No roadside assistance',
  'Linia de asistență NON-STOP': 'NON-STOP assistance line',
  'Dispeceratul service-ului': 'Service dispatch line',
  'NON-STOP, 24/7 — pentru clienții cu asistență rutieră activă.':
    'NON-STOP, 24/7 — for customers with active roadside assistance.',
  'Intervenția se facturează separat.': 'The intervention is billed separately.',
  'Nicio cerere': 'No requests',
  'Deschideți o cerere de asistență dacă aveți nevoie de ajutor pe drum.':
    'Open an assistance request if you need help on the road.',
  '← Asistență rutieră': '← Roadside assistance',
  'Cerere de asistență': 'Assistance request',
  'Locație:': 'Location:',
  'Problemă:': 'Problem:',
  'Mobilitate:': 'Mobility:',
  'Siguranță:': 'Safety:',
  'Telefon:': 'Phone:',
  'Anulează cererea': 'Cancel request',
  'Locația': 'Location',
  'Ex: DN13, km 12': 'E.g. DN13, km 12',
  'Problema': 'Problem',
  'Mașina se poate deplasa?': 'Can the car be driven?',
  'Da, se poate deplasa': 'Yes, it can be driven',
  'Nu, este imobilizată': 'No, it is immobilized',
  'Sunteți în siguranță?': 'Are you safe?',
  'Da, în siguranță': 'Yes, I am safe',
  'Nu, situație periculoasă': 'No, dangerous situation',
  'Telefon de contact': 'Contact phone',
  'Trimite cererea': 'Send request',

  // Mobilitate
  'Mobilitate': 'Mobility',
  'Aveți nevoie de mobilitate acum?': 'Need mobility right now?',
  'Trimiteți o cerere, sau sunați direct dispeceratul.': 'Send a request, or call the dispatch line directly.',
  '📞 Sună {phone}': '📞 Call {phone}',
  'Nicio solicitare': 'No requests yet',
  'Cereți o mașină de înlocuire, un taxi sau transport acasă.':
    'Request a replacement car, a taxi, or a ride home.',
  'Pentru {date}': 'For {date}',
  '← Mobilitate': '← Mobility',
  'Solicitarea nu este disponibilă.': 'This request is not available.',
  'Detalii:': 'Details:',
  'Data preferată:': 'Preferred date:',
  'Anulează solicitarea': 'Cancel request',
  'Solicitare de mobilitate': 'Mobility request',
  'Detalii': 'Details',
  'Data preferată (opțional)': 'Preferred date (optional)',
  'Trimite solicitarea': 'Send request',

  // Mesaje
  '+ Nou': '+ New',
  'Nicio conversație': 'No conversations',
  'Trimiteți un mesaj sau o cerere de ofertă service-ului.': 'Send the service a message or a quote request.',
  '← Mesaje': '← Messages',
  'Conversația nu este disponibilă.': 'This conversation is not available.',
  'Conversație închisă de service. Nu se mai pot trimite mesaje.':
    'Conversation closed by the service. No more messages can be sent.',
  'Răspunde': 'Reply',
  'Scrieți un mesaj…': 'Write a message…',
  'Mesaj nou': 'New message',
  'Subiect': 'Subject',
  'Mesaj': 'Message',

  // Oferte
  'Cere ofertă': 'Request a quote',
  'Nicio cerere de ofertă': 'No quote requests',
  'Descrieți problema și primiți o estimare de preț de la service.':
    'Describe the problem and get a price estimate from the service.',
  '← Cereri de ofertă': '← Quote requests',
  'Cerere de ofertă': 'Quote request',
  'Kilometraj:': 'Mileage:',
  'Când apare:': 'When it occurs:',
  'Conducibilă:': 'Drivable:',
  'Martori:': 'Warning lights:',
  'Interval preferat:': 'Preferred time slot:',
  'Răspunsul service-ului': 'Service reply',
  'Trimite cererea către service': 'Send request to the service',
  'Service-ul are nevoie de informații suplimentare. Contactați service-ul (telefon sau mesaj), apoi confirmați:':
    'The service needs more information. Contact the service (by phone or message), then confirm:',
  'Am completat informațiile': 'I have provided the information',
  'Acceptă oferta': 'Accept quote',
  'Refuză': 'Decline',
  'Cere ofertă de preț': 'Request a price quote',
  'Descrieți problema — service-ul revine cu o estimare. Fără obligații.':
    'Describe the problem — the service will reply with an estimate. No obligation.',
  'Kilometraj (opțional)': 'Mileage (optional)',
  'Descrierea simptomului *': 'Symptom description *',
  'Ex.: zgomot metalic la frânare, în față…': 'E.g. metallic noise when braking, at the front…',
  'Când apare (opțional)': 'When it occurs (optional)',
  'Ex.: la frânări puternice': 'E.g. under hard braking',
  'Mașina e conducibilă?': 'Is the car drivable?',
  'Martori aprinși în bord (opțional)': 'Dashboard warning lights on (optional)',
  'Ex.: ABS, motor': 'E.g. ABS, engine',
  'Contact preferat': 'Preferred contact method',
  'Telefon': 'Phone',
  'Mesaj în aplicație': 'In-app message',
  'Interval preferat (opțional)': 'Preferred time slot (optional)',
  'Ex.: luni–vineri după 16:00': 'E.g. Mon–Fri after 4 p.m.',
  'Salvează ca ciornă': 'Save as draft',

  // Înregistrare — revendicarea contului importat (P1-02)
  'Număr de înmatriculare (opțional)': 'Licence plate number (optional)',
  'Dacă sunteți deja client al service-ului, confirmați numărul de înmatriculare al mașinii — contul se leagă automat de vehiculele și istoricul dumneavoastră.':
    'If you are already a customer of the service, confirm your car’s licence plate — the account is linked automatically to your vehicles and service history.',
  'Acest email este deja în evidența service-ului. Introduceți numărul de înmatriculare al mașinii pentru a activa contul.':
    'This email is already in the service’s records. Enter your car’s licence plate number to activate the account.',
  'Numărul de înmatriculare nu corespunde evidenței service-ului.':
    'The licence plate number does not match the service’s records.',

  // PWA install (P2-01)
  'Instalează Companion pe telefon': 'Install Companion on your phone',
  'Accesează rapid cartea de service, documentele și mesajele service-ului.': 'Quick access to your service book, documents and workshop messages.',
  'Instalează': 'Install',
  'Închide': 'Close',
  'Instalează Companion pe ecranul telefonului': 'Add Companion to your home screen',
  'Apasă butonul Partajare din Safari.': 'Tap the Share button in Safari.',
  'Selectează „Adăugați la ecranul principal”.': 'Choose “Add to Home Screen”.',
  'Confirmă apăsând „Adăugați”.': 'Confirm by tapping “Add”.',
  // GDPR (P1-06)
  'Datele mele (GDPR)': 'My data (GDPR)',
  '⬇️ Descarcă datele mele (JSON)': '⬇️ Download my data (JSON)',
  'Șterge contul…': 'Delete account…',
  'Contul se blochează imediat, iar după 30 de zile datele personale se șterg definitiv. În acest interval vă puteți răzgândi contactând service-ul.':
    'The account is locked immediately, and after 30 days the personal data is permanently deleted. You can change your mind during this period by contacting the workshop.',
  'Confirmați parola': 'Confirm your password',
  'Confirm ștergerea contului': 'Confirm account deletion',
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
