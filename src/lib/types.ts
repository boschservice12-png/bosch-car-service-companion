export type Role = "client" | "admin";

export interface Client {
  id: string;
  nume: string;
  prenume: string;
  telefon: string;
  email: string;
  adresa: string;
  cnp?: string;
  creatLa: string;
}

export interface Vehicle {
  id: string;
  clientId: string;
  marca: string;
  model: string;
  an: number;
  vin: string;
  numarInmatriculare: string;
  km: number;
}

export type DeadlineType = "ITP" | "RCA" | "Rovinieta" | "Asistenta";

export interface Deadline {
  id: string;
  vehicleId: string;
  clientId: string;
  tip: DeadlineType;
  expiraLa: string; // ISO date
  detalii?: string;
  documentUrl?: string;
}

export interface ServiceEntry {
  id: string;
  vehicleId: string;
  clientId: string;
  data: string;
  km: number;
  tipLucrare: string;
  descriere: string;
  cost?: number;
  publicat: boolean;
  corectieDe?: string; // id of the entry it corrects
  motivCorectie?: string;
  autor: string;
  creatLa: string;
}

export type OfferStatus = "nou" | "in_analiza" | "oferta_trimisa" | "acceptata" | "refuzata";

export interface OfferRequest {
  id: string;
  clientId: string;
  vehicleId: string;
  descriere: string;
  urgenta: "scazuta" | "medie" | "ridicata";
  poze: string[];
  status: OfferStatus;
  ofertaText?: string;
  ofertaSuma?: number;
  creatLa: string;
}

export type AssistanceStatus = "nou" | "trimis_echipa" | "in_curs" | "finalizat" | "anulat";

export interface AssistanceRequest {
  id: string;
  clientId: string;
  vehicleId: string;
  locatie: string;
  descriere: string;
  telefonContact: string;
  status: AssistanceStatus;
  raspuns?: string;
  creatLa: string;
}

export type MobilityStatus = "nou" | "aprobat" | "respins" | "finalizat";

export interface MobilityRequest {
  id: string;
  clientId: string;
  vehicleId?: string;
  perioadaStart: string;
  perioadaEnd: string;
  tipMasina: string;
  motiv: string;
  status: MobilityStatus;
  raspuns?: string;
  creatLa: string;
}

export type DamageStatus = "deschis" | "documente_lipsa" | "trimis_asigurator" | "aprobat" | "respins" | "inchis";

export interface DamageFile {
  id: string;
  clientId: string;
  vehicleId: string;
  dataIncident: string;
  locatie: string;
  descriere: string;
  asigurator: string;
  numarDosar?: string;
  status: DamageStatus;
  pasi: { data: string; text: string; autor: string }[];
  documente: { nume: string; url: string }[];
  creatLa: string;
}

export interface Message {
  id: string;
  clientId: string;
  autor: Role;
  autorNume: string;
  text: string;
  timestamp: string;
  citit: boolean;
}

export type TaxStatus = "neplatit" | "platit" | "restant";

export interface Tax {
  id: string;
  clientId: string;
  vehicleId: string;
  an: number;
  tip: string; // impozit auto, taxa mediu, etc.
  suma: number;
  scadenta: string;
  status: TaxStatus;
  dovadaUrl?: string;
  platitLa?: string;
}

export interface Document {
  id: string;
  clientId: string;
  nume: string;
  tip: string;
  url: string;
  incarcatLa: string;
}

export interface AuditLog {
  id: string;
  timestamp: string;
  autor: string;
  rol: Role;
  actiune: string;
  entitate: string;
  detalii?: string;
}

export interface AppData {
  clients: Client[];
  vehicles: Vehicle[];
  deadlines: Deadline[];
  serviceHistory: ServiceEntry[];
  offers: OfferRequest[];
  assistance: AssistanceRequest[];
  mobility: MobilityRequest[];
  damages: DamageFile[];
  messages: Message[];
  taxes: Tax[];
  documents: Document[];
  audit: AuditLog[];
}