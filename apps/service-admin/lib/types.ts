export interface Me {
  id: string;
  email: string;
  role: 'CLIENT' | 'SERVICE_ADMIN';
  name: string | null;
  /** P0-06: 2FA activ pe cont. */
  totpEnabled?: boolean;
  /** P0-06: sesiunea așteaptă al doilea factor (login neterminat). */
  requiresOtp?: boolean;
}

export interface AdminVehicle {
  id: string;
  vin: string;
  plateNumber: string;
  make: string | null;
  model: string | null;
  year: number | null;
  ownerName: string | null;
}

export type DeadlineType = 'ITP' | 'RCA' | 'ROAD_TAX' | 'ROADSIDE_ASSISTANCE';
export type DeadlineState = 'UNKNOWN' | 'VALID' | 'DUE_SOON' | 'EXPIRED';

export type ScanStatus = 'PENDING' | 'CLEAN' | 'INFECTED';

export interface DeadlineDocument {
  id: string;
  originalName: string | null;
  mimeType: string;
  sizeBytes: number;
  scanStatus: ScanStatus;
  servable: boolean;
}

export interface Deadline {
  id: string;
  type: DeadlineType;
  typeLabel: string;
  expiresAt: string | null;
  state: DeadlineState;
  stateLabel: string;
  daysLeft: number | null;
  source: string;
  verified: boolean;
  note: string | null;
  documentId: string | null;
  document: DeadlineDocument | null;
}

/** Limitele de upload — trebuie să corespundă backend-ului (SettingsProvider). */
export const UPLOAD_MAX_BYTES = 10 * 1024 * 1024; // 10 MB
export const UPLOAD_ACCEPT: Record<string, string> = {
  'image/jpeg': '.jpg,.jpeg',
  'image/png': '.png',
  'image/webp': '.webp',
  'application/pdf': '.pdf',
};

export type ServiceRecordStatus = 'DRAFT' | 'PUBLISHED' | 'CORRECTED';

export interface ServiceRecord {
  id: string;
  vehicleId: string;
  status: ServiceRecordStatus;
  correctionReason: string | null;
  statusLabel: string;
  serviceDate: string | null;
  odometerKm: number | null;
  workType: string | null;
  workDescription: string | null;
  partsSummary: string | null;
  laborCost: number;
  totalAmount: number;
  warranty: string | null;
  correctionOfId: string | null;
  corrected: boolean;
  publishedAt: string | null;
  createdAt: string;
  documents: DeadlineDocument[];
}

/** Payload pentru crearea/editarea unei ciorne. Câmpurile goale se omit. */
export interface ServiceRecordInput {
  serviceDate?: string;
  odometerKm?: number;
  workType?: string;
  workDescription?: string;
  partsSummary?: string;
  laborCost?: number;
  totalAmount?: number;
  warranty?: string;
}

export type ConversationStatus = 'OPEN' | 'WAITING_CLIENT' | 'WAITING_SERVICE' | 'CLOSED';
export type MessageAuthorRole = 'CLIENT' | 'ADMIN';

export interface ConversationMessage {
  id: string;
  authorRole: MessageAuthorRole;
  authorLabel: string;
  body: string;
  createdAt: string;
  attachments: DeadlineDocument[];
}

export interface Conversation {
  id: string;
  subject: string;
  status: ConversationStatus;
  statusLabel: string;
  vehicleId: string | null;
  vehiclePlate: string | null;
  messageCount: number;
  lastMessagePreview: string | null;
  createdAt: string;
  lastMessageAt: string;
  customerName?: string;
  messages?: ConversationMessage[];
}

export type MobilityState = 'DRIVABLE' | 'NOT_DRIVABLE';
export type SafetyState = 'SAFE' | 'AT_RISK';
export type RoadsideStatus = 'SUBMITTED' | 'VALIDATED' | 'FORWARDED' | 'IN_PROGRESS' | 'COMPLETED' | 'CANCELLED';

export interface RoadsideRequest {
  id: string;
  vehicleId: string | null;
  vehiclePlate: string | null;
  location: string;
  problem: string;
  mobility: MobilityState;
  mobilityLabel: string;
  safety: SafetyState;
  safetyLabel: string;
  phone: string;
  status: RoadsideStatus;
  statusLabel: string;
  note: string | null;
  createdAt: string;
  documents: DeadlineDocument[];
  customerName?: string;
}

export type MobilityType = 'REPLACEMENT_CAR' | 'TAXI' | 'PERSON_TRANSPORT' | 'ACCOMMODATION' | 'OTHER';
export type MobilityStatus = 'SUBMITTED' | 'IN_REVIEW' | 'CONTACTED' | 'CONFIRMED' | 'UNAVAILABLE' | 'COMPLETED' | 'CANCELLED';

export interface MobilityRequest {
  id: string;
  vehicleId: string | null;
  vehiclePlate: string | null;
  type: MobilityType;
  typeLabel: string;
  details: string;
  preferredDate: string | null;
  status: MobilityStatus;
  statusLabel: string;
  note: string | null;
  createdAt: string;
  customerName?: string;
}

export type DamageClaimStatus = 'SUBMITTED' | 'DOCUMENTS_MISSING' | 'IN_REVIEW' | 'CONTACTED' | 'FILE_OPENED' | 'CLOSED';

export interface DamageClaim {
  id: string;
  vehicleId: string | null;
  vehiclePlate: string | null;
  incidentDate: string | null;
  incidentLocation: string | null;
  incidentDescription: string;
  insurer: string | null;
  policyNumber: string | null;
  status: DamageClaimStatus;
  missingDocuments: string[] | null;
  statusLabel: string;
  note: string | null;
  createdAt: string;
  documents: DeadlineDocument[];
  customerName?: string;
}

export type TaxType = 'VEHICLE_TAX' | 'ENVIRONMENT' | 'OTHER';
export type PaymentStatus = 'UNPAID' | 'PARTIALLY_PAID' | 'PAID' | 'OVERDUE';

export interface TaxItem {
  id: string;
  vehicleId: string | null;
  vehiclePlate: string | null;
  year: number;
  type: TaxType;
  typeLabel: string;
  amount: number;
  dueDate: string | null;
  status: PaymentStatus;
  statusLabel: string;
  paidAmount: number | null;
  paidAt: string | null;
  note: string | null;
  createdAt: string;
  customerName?: string;
}

export interface ApiProblem {
  type: string;
  title: string;
  status: number;
  traceId: string;
  errors?: Record<string, string[]>;
}

export class ApiError extends Error {
  constructor(
    public readonly problem: ApiProblem,
    public readonly httpStatus: number,
  ) {
    super(problem.title);
    this.name = 'ApiError';
  }
}

// ---- Cereri de ofertă (modul propriu, specificație pct. 7) ----
export type QuoteRequestStatus =
  | 'DRAFT' | 'SUBMITTED' | 'IN_REVIEW' | 'NEEDS_INFORMATION'
  | 'REPLIED' | 'ACCEPTED' | 'DECLINED' | 'CLOSED';

export interface QuoteResponse {
  id: string;
  message: string;
  document: { id: string; originalName: string | null; mimeType: string; servable: boolean } | null;
  createdAt: string;
}

export interface QuoteRequest {
  id: string;
  vehicleId: string | null;
  vehiclePlate: string | null;
  mileage: number | null;
  symptomDescription: string;
  occurrenceConditions: string | null;
  vehicleDrivable: boolean;
  warningLights: string | null;
  preferredContactMethod: string | null;
  preferredInterval: string | null;
  status: QuoteRequestStatus;
  statusLabel: string;
  responses: QuoteResponse[];
  createdAt: string;
  updatedAt: string;
  customerName?: string;
}

// ---- Import clienți + vehicule (Excel/CSV) ----
export interface ImportReport {
  totalRows: number;
  errorCount?: number;
  ownersCreated: number;
  vehiclesCreated: number;
  vehiclesUpdated: number;
  ownershipsCreated: number;
  errors: { row: number; message: string }[];
}

export interface DeadlineImportReport {
  totalRows: number;
  deadlinesCreated: number;
  deadlinesUpdated: number;
  rowsSkipped: number;
  nonItpRcaSkipped: number;
  errorCount: number;
  errors: { row: number; message: string }[];
}

export interface HistoryImportReport {
  totalRows: number;
  recordsPublished: number;
  recordsDraft: number;
  recordsSkipped: number;
  errors: { row: number; message: string }[];
}
