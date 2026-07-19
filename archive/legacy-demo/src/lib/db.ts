import { useSyncExternalStore } from "react";
import type { AppData, AuditLog } from "./types";

const KEY = "bcs_data_v1";

function seed(): AppData {
  const now = new Date();
  const daysFromNow = (d: number) => {
    const x = new Date(now);
    x.setDate(x.getDate() + d);
    return x.toISOString().slice(0, 10);
  };
  const clientId = "c1";
  const c2 = "c2";
  const v1 = "v1";
  const v2 = "v2";
  const v3 = "v3";
  return {
    clients: [
      { id: clientId, nume: "Popescu", prenume: "Andrei", telefon: "0722 123 456", email: "andrei.popescu@example.ro", adresa: "Str. Aviatorilor 12, București", cnp: "1850101010011", creatLa: "2022-03-14" },
      { id: c2, nume: "Ionescu", prenume: "Maria", telefon: "0733 987 654", email: "maria.ionescu@example.ro", adresa: "Str. Libertății 5, Cluj-Napoca", creatLa: "2023-09-02" },
    ],
    vehicles: [
      { id: v1, clientId, marca: "Dacia", model: "Duster", an: 2019, vin: "UU1HSDJTG60123456", numarInmatriculare: "B 12 ABC", km: 87400 },
      { id: v2, clientId, marca: "Volkswagen", model: "Golf 7", an: 2016, vin: "WVWZZZAUZGW123456", numarInmatriculare: "B 345 XYZ", km: 142300 },
      { id: v3, clientId: c2, marca: "Skoda", model: "Octavia", an: 2020, vin: "TMBJJ7NE5L1234567", numarInmatriculare: "CJ 07 MAR", km: 62100 },
    ],
    deadlines: [
      { id: "d1", vehicleId: v1, clientId, tip: "ITP", expiraLa: daysFromNow(18) },
      { id: "d2", vehicleId: v1, clientId, tip: "RCA", expiraLa: daysFromNow(-4) },
      { id: "d3", vehicleId: v1, clientId, tip: "Rovinieta", expiraLa: daysFromNow(120) },
      { id: "d4", vehicleId: v1, clientId, tip: "Asistenta", expiraLa: daysFromNow(75) },
      { id: "d5", vehicleId: v2, clientId, tip: "ITP", expiraLa: daysFromNow(210) },
      { id: "d6", vehicleId: v2, clientId, tip: "RCA", expiraLa: daysFromNow(45) },
      { id: "d7", vehicleId: v2, clientId, tip: "Rovinieta", expiraLa: daysFromNow(9) },
      { id: "d8", vehicleId: v2, clientId, tip: "Asistenta", expiraLa: daysFromNow(-30) },
      { id: "d9", vehicleId: v3, clientId: c2, tip: "ITP", expiraLa: daysFromNow(55) },
      { id: "d10", vehicleId: v3, clientId: c2, tip: "RCA", expiraLa: daysFromNow(200) },
    ],
    serviceHistory: [
      { id: "s1", vehicleId: v1, clientId, data: "2022-05-10", km: 32000, tipLucrare: "Revizie 30.000 km", descriere: "Schimb ulei motor 5W30, filtru ulei, filtru aer, filtru polen, verificare frâne.", cost: 780, publicat: true, autor: "Service", creatLa: "2022-05-10T10:00:00Z" },
      { id: "s2", vehicleId: v1, clientId, data: "2023-04-22", km: 58500, tipLucrare: "Revizie 60.000 km", descriere: "Schimb ulei, filtre, plăcuțe frână față, lichid frână.", cost: 1450, publicat: true, autor: "Service", creatLa: "2023-04-22T09:30:00Z" },
      { id: "s3", vehicleId: v1, clientId, data: "2024-01-15", km: 74200, tipLucrare: "Reparație climatizare", descriere: "Înlocuire compresor A/C, încărcare freon.", cost: 2300, publicat: true, autor: "Service", creatLa: "2024-01-15T11:00:00Z" },
      { id: "s4", vehicleId: v2, clientId, data: "2021-11-03", km: 98000, tipLucrare: "Schimb distribuție", descriere: "Kit distribuție complet + pompă apă.", cost: 3200, publicat: true, autor: "Service", creatLa: "2021-11-03T08:00:00Z" },
      { id: "s5", vehicleId: v2, clientId, data: "2024-06-18", km: 135000, tipLucrare: "Revizie majoră", descriere: "Ulei, filtre, bujii, verificare suspensie.", cost: 1800, publicat: true, autor: "Service", creatLa: "2024-06-18T14:00:00Z" },
    ],
    offers: [
      { id: "o1", clientId, vehicleId: v1, descriere: "Zgomot la trecerea peste denivelări, față dreapta.", urgenta: "medie", poze: [], status: "oferta_trimisa", ofertaText: "Diagnoză suspensie + eventuală înlocuire braț. Estimare piese: 650 RON, manoperă: 350 RON.", ofertaSuma: 1000, creatLa: "2025-11-02T10:00:00Z" },
    ],
    assistance: [],
    mobility: [],
    damages: [
      { id: "dm1", clientId, vehicleId: v1, dataIncident: "2024-09-10", locatie: "Intersecție Bd. Timișoara / Str. Brașov", descriere: "Impact spate în trafic la semafor.", asigurator: "Allianz-Țiriac", numarDosar: "AT-2024-88112", status: "trimis_asigurator", pasi: [ { data: "2024-09-10", text: "Deschis dosar, primite fotografii.", autor: "Service" }, { data: "2024-09-12", text: "Trimis dosar la asigurător.", autor: "Service" } ], documente: [], creatLa: "2024-09-10T15:00:00Z" },
    ],
    messages: [
      { id: "m1", clientId, autor: "admin", autorNume: "Service Bosch", text: "Bine ați venit în portalul clientului. Suntem la dispoziția dvs.", timestamp: "2025-10-01T08:00:00Z", citit: true },
      { id: "m2", clientId, autor: "client", autorNume: "Andrei Popescu", text: "Bună ziua, aș dori o programare pentru revizie săptămâna viitoare.", timestamp: "2025-11-05T09:15:00Z", citit: true },
      { id: "m3", clientId, autor: "admin", autorNume: "Service Bosch", text: "Bună ziua! Avem disponibilitate marți la 09:00 sau joi la 14:00. Confirmați?", timestamp: "2025-11-05T10:30:00Z", citit: false },
    ],
    taxes: [
      { id: "t1", clientId, vehicleId: v1, an: 2025, tip: "Impozit auto", suma: 145, scadenta: "2025-03-31", status: "platit", platitLa: "2025-02-15" },
      { id: "t2", clientId, vehicleId: v1, an: 2026, tip: "Impozit auto", suma: 145, scadenta: "2026-03-31", status: "neplatit" },
      { id: "t3", clientId, vehicleId: v2, an: 2025, tip: "Impozit auto", suma: 220, scadenta: "2025-03-31", status: "platit", platitLa: "2025-03-20" },
      { id: "t4", clientId, vehicleId: v2, an: 2026, tip: "Impozit auto", suma: 220, scadenta: "2026-03-31", status: "neplatit" },
    ],
    documents: [],
    audit: [
      { id: "a1", timestamp: "2025-11-05T10:30:00Z", autor: "Service Bosch", rol: "admin", actiune: "Răspuns mesaj", entitate: "Mesaj m3" },
    ],
  };
}

let cache: AppData | null = null;
const listeners = new Set<() => void>();

function read(): AppData {
  if (cache) return cache;
  if (typeof window === "undefined") {
    cache = seed();
    return cache;
  }
  const raw = window.localStorage.getItem(KEY);
  if (raw) {
    try {
      cache = JSON.parse(raw) as AppData;
      return cache;
    } catch {
      /* ignore */
    }
  }
  cache = seed();
  window.localStorage.setItem(KEY, JSON.stringify(cache));
  return cache;
}

function write(next: AppData) {
  cache = next;
  if (typeof window !== "undefined") {
    window.localStorage.setItem(KEY, JSON.stringify(next));
  }
  listeners.forEach((l) => l());
}

export function getData(): AppData {
  return read();
}

export function useData(): AppData {
  return useSyncExternalStore(
    (cb) => {
      listeners.add(cb);
      return () => listeners.delete(cb);
    },
    () => read(),
    () => read(),
  );
}

export function update(mutator: (d: AppData) => void) {
  const next = structuredClone(read());
  mutator(next);
  write(next);
}

export function uid(prefix = "id"): string {
  return `${prefix}_${Math.random().toString(36).slice(2, 9)}`;
}

export function audit(entry: Omit<AuditLog, "id" | "timestamp">) {
  update((d) => {
    d.audit.unshift({
      ...entry,
      id: uid("a"),
      timestamp: new Date().toISOString(),
    });
  });
}

export function resetDemoData() {
  cache = seed();
  if (typeof window !== "undefined") {
    window.localStorage.setItem(KEY, JSON.stringify(cache));
  }
  listeners.forEach((l) => l());
}

export function fileToDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const r = new FileReader();
    r.onload = () => resolve(String(r.result));
    r.onerror = () => reject(r.error);
    r.readAsDataURL(file);
  });
}