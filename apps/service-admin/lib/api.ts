import {
  ApiError,
  type AdminVehicle,
  type ApiProblem,
  type Conversation,
  type Deadline,
  type DamageClaim,
  type Me,
  type MobilityRequest,
  type RoadsideRequest,
  type ServiceRecord,
  type ServiceRecordInput,
  type TaxItem,
} from './types';

/** URL (same-origin, proxy /api) pentru descărcarea unui document de service. */
export function serviceRecordDocumentHref(recordId: string, documentId: string): string {
  return `/api/service-records/${recordId}/documents/${documentId}`;
}

/** URL (same-origin) pentru descărcarea unui atașament dintr-o conversație. */
export function conversationDocumentHref(conversationId: string, documentId: string): string {
  return `/api/conversations/${conversationId}/documents/${documentId}`;
}

/** URL (same-origin) pentru descărcarea unui atașament dintr-o cerere de asistență. */
export function roadsideDocumentHref(requestId: string, documentId: string): string {
  return `/api/roadside-requests/${requestId}/documents/${documentId}`;
}

/** URL (same-origin) pentru descărcarea unui document dintr-un dosar de daună. */
export function damageClaimDocumentHref(claimId: string, documentId: string): string {
  return `/api/damage-claims/${claimId}/documents/${documentId}`;
}

/** URL (same-origin) pentru descărcarea bizonjatului unei taxe. */
export function taxDocumentHref(taxId: string, documentId: string): string {
  return `/api/taxes/${taxId}/documents/${documentId}`;
}

/** Metadatele unui document încărcat. */
export interface UploadedDocument {
  id: string;
  mimeType: string;
  sizeBytes: number;
  scanStatus: string;
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  let res: Response;
  try {
    res = await fetch(`/api${path}`, {
      ...init,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(init?.headers ?? {}),
      },
    });
  } catch {
    throw new ApiError({ type: 'network_error', title: 'Conexiune indisponibilă.', status: 0, traceId: '' }, 0);
  }

  if (res.status === 204) return undefined as T;
  const body = (await res.json().catch(() => null)) as unknown;
  if (!res.ok) {
    const problem = (body as ApiProblem | null) ?? { type: 'error', title: 'Eroare.', status: res.status, traceId: '' };
    throw new ApiError(problem, res.status);
  }
  return body as T;
}

export const api = {
  me: () => request<Me>('/me'),
  login: (email: string, password: string) =>
    request<{ id: string; role: string }>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
  logout: () => request<void>('/auth/logout', { method: 'POST' }),

  adminVehicles: () => request<AdminVehicle[]>('/admin/vehicles'),

  vehicleDeadlines: (vehicleId: string) => request<Deadline[]>(`/vehicles/${vehicleId}/deadlines`),

  createDeadline: (vehicleId: string, data: { type: string; expiresAt: string; note?: string }) =>
    request<Deadline>(`/vehicles/${vehicleId}/deadlines`, { method: 'POST', body: JSON.stringify(data) }),

  verifyDeadline: (deadlineId: string) =>
    request<Deadline>(`/deadlines/${deadlineId}`, { method: 'PATCH', body: JSON.stringify({ verify: true }) }),

  /** Încărcare fișier (multipart). NU setăm Content-Type — browserul adaugă boundary-ul. */
  uploadDocument: (file: File) => {
    const form = new FormData();
    form.append('file', file);
    return uploadRequest<UploadedDocument>('/documents', form);
  },

  attachDocument: (deadlineId: string, documentId: string) =>
    request<Deadline>(`/deadlines/${deadlineId}/documents`, {
      method: 'POST',
      body: JSON.stringify({ documentId }),
    }),

  documentDownloadUrl: (documentId: string) =>
    request<{ url: string; expiresAt: string }>(`/documents/${documentId}/download-url`),

  serviceRecords: (vehicleId: string) => request<ServiceRecord[]>(`/admin/vehicles/${vehicleId}/service-records`),

  createServiceRecord: (vehicleId: string, data: ServiceRecordInput) =>
    request<ServiceRecord>(`/admin/vehicles/${vehicleId}/service-records`, {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  updateServiceRecord: (id: string, data: ServiceRecordInput) =>
    request<ServiceRecord>(`/admin/service-records/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),

  publishServiceRecord: (id: string) =>
    request<ServiceRecord>(`/admin/service-records/${id}/publish`, { method: 'POST' }),

  correctServiceRecord: (id: string) =>
    request<ServiceRecord>(`/admin/service-records/${id}/corrections`, { method: 'POST' }),

  attachServiceRecordDocument: (id: string, documentId: string) =>
    request<ServiceRecord>(`/admin/service-records/${id}/documents`, {
      method: 'POST',
      body: JSON.stringify({ documentId }),
    }),

  conversations: () => request<Conversation[]>('/admin/conversations'),

  conversation: (id: string) => request<Conversation>(`/admin/conversations/${id}`),

  reply: (id: string, data: { body: string; documentIds?: string[] }) =>
    request<Conversation>(`/admin/conversations/${id}/messages`, { method: 'POST', body: JSON.stringify(data) }),

  quote: (id: string, data: { amount: number; body?: string }) =>
    request<Conversation>(`/admin/conversations/${id}/quote`, { method: 'POST', body: JSON.stringify(data) }),

  roadsideRequests: () => request<RoadsideRequest[]>('/admin/roadside-requests'),

  roadsideRequest: (id: string) => request<RoadsideRequest>(`/admin/roadside-requests/${id}`),

  updateRoadsideStatus: (id: string, data: { status: string; note?: string }) =>
    request<RoadsideRequest>(`/admin/roadside-requests/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),

  mobilityRequests: () => request<MobilityRequest[]>('/admin/mobility-requests'),

  mobilityRequest: (id: string) => request<MobilityRequest>(`/admin/mobility-requests/${id}`),

  updateMobilityStatus: (id: string, data: { status: string; note?: string }) =>
    request<MobilityRequest>(`/admin/mobility-requests/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),

  damageClaims: () => request<DamageClaim[]>('/admin/damage-claims'),

  damageClaim: (id: string) => request<DamageClaim>(`/admin/damage-claims/${id}`),

  updateDamageClaimStatus: (id: string, data: { status: string; note?: string }) =>
    request<DamageClaim>(`/admin/damage-claims/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),

  taxes: () => request<TaxItem[]>('/admin/taxes'),

  tax: (id: string) => request<TaxItem>(`/admin/taxes/${id}`),

  updateTaxStatus: (id: string, data: { status: string; note?: string }) =>
    request<TaxItem>(`/admin/taxes/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),
};

/** Variantă multipart a `request` (fără header JSON), pentru upload de fișiere. */
async function uploadRequest<T>(path: string, body: FormData): Promise<T> {
  let res: Response;
  try {
    res = await fetch(`/api${path}`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body,
    });
  } catch {
    throw new ApiError({ type: 'network_error', title: 'Conexiune indisponibilă.', status: 0, traceId: '' }, 0);
  }

  const parsed = (await res.json().catch(() => null)) as unknown;
  if (!res.ok) {
    const problem = (parsed as ApiProblem | null) ?? { type: 'error', title: 'Încărcare eșuată.', status: res.status, traceId: '' };
    throw new ApiError(problem, res.status);
  }

  return parsed as T;
}
