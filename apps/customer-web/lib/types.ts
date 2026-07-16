export type Role = 'CLIENT' | 'SERVICE_ADMIN';

export interface Me {
  id: string;
  email: string;
  role: Role;
  name: string | null;
  totpEnabled: boolean;
}

export interface Vehicle {
  id: string;
  vin: string;
  plateNumber: string;
  make: string | null;
  model: string | null;
  year: number | null;
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
  validFrom: string | null;
  expiresAt: string | null;
  state: DeadlineState;
  stateLabel: string;
  daysLeft: number | null;
  source: 'CLIENT' | 'SERVICE' | 'IMPORT';
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

export type ServiceRecordStatus = 'DRAFT' | 'PUBLISHED';

export interface ServiceRecord {
  id: string;
  vehicleId: string;
  status: ServiceRecordStatus;
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

export type ConversationType = 'GENERAL' | 'QUOTE';
export type ConversationStatus = 'OPEN' | 'QUOTED' | 'ACCEPTED' | 'DECLINED' | 'CLOSED';
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
  type: ConversationType;
  typeLabel: string;
  subject: string;
  status: ConversationStatus;
  statusLabel: string;
  vehicleId: string | null;
  vehiclePlate: string | null;
  quoteAmount: number | null;
  messageCount: number;
  lastMessagePreview: string | null;
  createdAt: string;
  lastMessageAt: string;
  customerName?: string;
  messages?: ConversationMessage[];
}

export type MobilityState = 'DRIVABLE' | 'NOT_DRIVABLE';
export type SafetyState = 'SAFE' | 'AT_RISK';
export type RoadsideStatus = 'NEW' | 'FORWARDED' | 'RESOLVED' | 'CANCELLED';

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

export type MobilityType = 'REPLACEMENT_CAR' | 'TAXI' | 'RIDE_HOME' | 'OTHER';
export type MobilityStatus = 'NEW' | 'APPROVED' | 'PROVIDED' | 'DECLINED' | 'CANCELLED';

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

export type DamageClaimStatus = 'NEW' | 'IN_PROGRESS' | 'CLOSED' | 'CANCELLED';

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
  statusLabel: string;
  note: string | null;
  createdAt: string;
  documents: DeadlineDocument[];
  customerName?: string;
}

/** Structura standard de eroare (application/problem+json). */
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

  /** Erori per câmp, pentru afișare lângă inputuri. */
  fieldErrors(): Record<string, string[]> {
    return this.problem.errors ?? {};
  }
}
