import {
  type HistoryImportReport,
  type ImportReport,
  type QuoteRequest,
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

/** Metadatele unui document încărcat. */
export interface UploadedDocument {
  id: string;
  mimeType: string;
  sizeBytes: number;
  scanStatus: string;
}


/** P0-05 — CSRF „double submit": citește cookie-ul `bcsc_csrf` și trimite-l ca antet. */
function readCsrfCookie(): string | null {
  if (typeof document === 'undefined') return null;
  const value = document.cookie.match(/(?:^|; )bcsc_csrf=([^;]+)/)?.[1];
  return value ? decodeURIComponent(value) : null;
}

async function ensureCsrfToken(): Promise<string> {
  let token = readCsrfCookie();
  if (!token) {
    await fetch('/api/csrf', { credentials: 'include' }).catch(() => undefined);
    token = readCsrfCookie();
  }
  return token ?? '';
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const method = (init?.method ?? 'GET').toUpperCase();
  const csrf = method === 'GET' ? null : await ensureCsrfToken();
  let res: Response;
  try {
    res = await fetch(`/api${path}`, {
      ...init,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrf ? { 'X-CSRF-Token': csrf } : {}),
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
    request<{ id: string; role: string; requiresOtp?: boolean }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),
  logout: () => request<void>('/auth/logout', { method: 'POST' }),

  // ---- P0-06: autentificare în doi factori (TOTP) ----
  setup2fa: (password: string) =>
    request<{ secret: string; otpauthUri: string }>('/auth/2fa/setup', {
      method: 'POST',
      body: JSON.stringify({ password }),
    }),

  enable2fa: (code: string) =>
    request<{ twoFactorEnabled: boolean; recoveryCodes: string[] }>('/auth/2fa/enable', {
      method: 'POST',
      body: JSON.stringify({ code }),
    }),

  verify2fa: (payload: { code?: string; recoveryCode?: string }) =>
    request<{ verified: boolean; recoveryCodesLeft?: number }>('/auth/2fa/verify', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  disable2fa: (code: string) =>
    request<{ twoFactorEnabled: boolean }>('/auth/2fa/disable', { method: 'POST', body: JSON.stringify({ code }) }),

  adminVehicles: () => request<AdminVehicle[]>('/admin/vehicles'),

  vehicleDeadlines: (vehicleId: string) => request<Deadline[]>(`/vehicles/${vehicleId}/deadlines`),

  createDeadline: (vehicleId: string, data: { type: string; expiresAt: string; note?: string }) =>
    request<Deadline>(`/vehicles/${vehicleId}/deadlines`, { method: 'POST', body: JSON.stringify(data) }),

  verifyDeadline: (deadlineId: string) =>
    request<Deadline>(`/deadlines/${deadlineId}`, { method: 'PATCH', body: JSON.stringify({ verify: true }) }),

  /** Încărcare fișier (multipart). NU setăm Content-Type — browserul adaugă boundary-ul. */
  /** Import clienți + vehicule din Excel/CSV (multipart). */
  importClients: (file: File) => {
    const form = new FormData();
    form.append('file', file);
    return uploadRequest<ImportReport>('/admin/import/clients', form);
  },

  /** Import istoric reparații (multipart), legat de vehicule prin VIN. */
  importServiceHistory: (file: File) => {
    const form = new FormData();
    form.append('file', file);
    return uploadRequest<HistoryImportReport>('/admin/import/service-history', form);
  },

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

  correctServiceRecord: (id: string, reason: string) =>
    request<ServiceRecord>(`/admin/service-records/${id}/corrections`, { method: 'POST', body: JSON.stringify({ reason }) }),

  attachServiceRecordDocument: (id: string, documentId: string) =>
    request<ServiceRecord>(`/admin/service-records/${id}/documents`, {
      method: 'POST',
      body: JSON.stringify({ documentId }),
    }),

  conversations: () => request<Conversation[]>('/admin/conversations'),

  conversation: (id: string) => request<Conversation>(`/admin/conversations/${id}`),

  reply: (id: string, data: { body: string; documentIds?: string[] }) =>
    request<Conversation>(`/admin/conversations/${id}/messages`, { method: 'POST', body: JSON.stringify(data) }),

  closeConversation: (id: string) => request<Conversation>(`/admin/conversations/${id}/close`, { method: 'POST' }),

  reopenConversation: (id: string) => request<Conversation>(`/admin/conversations/${id}/reopen`, { method: 'POST' }),

  // ---- Cereri de ofertă (specificație pct. 7) ----
  quoteRequests: () => request<QuoteRequest[]>('/admin/quote-requests'),

  quoteRequest: (id: string) => request<QuoteRequest>(`/admin/quote-requests/${id}`),

  quoteRequestStatus: (id: string, status: string) =>
    request<QuoteRequest>(`/admin/quote-requests/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) }),

  respondQuoteRequest: (id: string, data: { message: string; documentId?: string | null }) =>
    request<QuoteRequest>(`/admin/quote-requests/${id}/response`, { method: 'POST', body: JSON.stringify(data) }),

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
  const csrf = await ensureCsrfToken();
  let res: Response;
  try {
    res = await fetch(`/api${path}`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
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
