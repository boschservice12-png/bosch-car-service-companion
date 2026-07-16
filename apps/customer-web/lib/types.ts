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
