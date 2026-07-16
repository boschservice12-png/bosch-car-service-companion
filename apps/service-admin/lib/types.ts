export interface Me {
  id: string;
  email: string;
  role: 'CLIENT' | 'SERVICE_ADMIN';
  name: string | null;
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
