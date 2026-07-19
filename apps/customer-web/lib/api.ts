import {
  type QuoteRequest,
  ApiError,
  type ApiProblem,
  type Conversation,
  type Deadline,
  type Me,
  type MobilityRequest,
  type RoadsideRequest,
  type ServiceRecord,
  type TaxItem,
  type Vehicle,
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

/** Metadatele unui document încărcat. */
export interface UploadedDocument {
  id: string;
  mimeType: string;
  sizeBytes: number;
  scanStatus: string;
}

/**
 * Client API. Folosește cookie de sesiune (httpOnly) — de aceea toate cererile
 * merg cu `credentials: 'include'`. Rutele `/api/*` sunt proxy-ate către backend
 * (vezi next.config.mjs), deci path-uri relative.
 */

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
    throw new ApiError(
      { type: 'network_error', title: 'Conexiune indisponibilă. Verificați internetul.', status: 0, traceId: '' },
      0,
    );
  }

  if (res.status === 204) {
    return undefined as T;
  }

  const body = (await res.json().catch(() => null)) as unknown;

  if (!res.ok) {
    const problem = (body as ApiProblem | null) ?? {
      type: 'error',
      title: 'A apărut o eroare.',
      status: res.status,
      traceId: '',
    };
    throw new ApiError(problem, res.status);
  }

  return body as T;
}

export const api = {
  me: () => request<Me>('/me'),

  login: (email: string, password: string) =>
    request<{ id: string; email: string; role: string }>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    }),

  logout: () => request<void>('/auth/logout', { method: 'POST' }),

  register: (data: { email: string; password: string; firstName?: string; lastName?: string; consent: boolean }) =>
    request<{ id: string }>('/auth/register', { method: 'POST', body: JSON.stringify(data) }),

  vehicles: () => request<Vehicle[]>('/vehicles'),

  createVehicle: (data: { vin: string; plateNumber: string; make?: string; model?: string; year?: number }) =>
    request<Vehicle>('/vehicles', { method: 'POST', body: JSON.stringify(data) }),

  vehicleDeadlines: (vehicleId: string) => request<Deadline[]>(`/vehicles/${vehicleId}/deadlines`),

  createDeadline: (
    vehicleId: string,
    data: { type: string; expiresAt: string; validFrom?: string; note?: string },
  ) => request<Deadline>(`/vehicles/${vehicleId}/deadlines`, { method: 'POST', body: JSON.stringify(data) }),

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

  serviceRecords: (vehicleId: string) => request<ServiceRecord[]>(`/vehicles/${vehicleId}/service-records`),

  serviceRecord: (id: string) => request<ServiceRecord>(`/service-records/${id}`),

  conversations: () => request<Conversation[]>('/conversations'),

  conversation: (id: string) => request<Conversation>(`/conversations/${id}`),

  startConversation: (data: {
    subject: string;
    body: string;
    vehicleId?: string;
    documentIds?: string[];
  }) => request<Conversation>('/conversations', { method: 'POST', body: JSON.stringify(data) }),

  postMessage: (id: string, data: { body: string; documentIds?: string[] }) =>
    request<Conversation>(`/conversations/${id}/messages`, { method: 'POST', body: JSON.stringify(data) }),

  // ---- Cereri de ofertă (specificație pct. 7) ----
  quoteRequests: () => request<QuoteRequest[]>('/quote-requests'),

  quoteRequest: (id: string) => request<QuoteRequest>(`/quote-requests/${id}`),

  createQuoteRequest: (data: {
    vehicleId?: string | null;
    mileage?: number | null;
    symptomDescription: string;
    occurrenceConditions?: string | null;
    vehicleDrivable: boolean;
    warningLights?: string | null;
    preferredContactMethod?: string | null;
    preferredInterval?: string | null;
    draft?: boolean;
  }) => request<QuoteRequest>('/quote-requests', { method: 'POST', body: JSON.stringify(data) }),

  submitQuoteRequest: (id: string) => request<QuoteRequest>(`/quote-requests/${id}/submit`, { method: 'POST' }),

  resubmitQuoteRequest: (id: string) => request<QuoteRequest>(`/quote-requests/${id}/resubmit`, { method: 'POST' }),

  acceptQuoteRequest: (id: string) => request<QuoteRequest>(`/quote-requests/${id}/accept`, { method: 'POST' }),

  declineQuoteRequest: (id: string) => request<QuoteRequest>(`/quote-requests/${id}/decline`, { method: 'POST' }),

  roadsideRequests: () => request<RoadsideRequest[]>('/roadside-requests'),

  roadsideRequest: (id: string) => request<RoadsideRequest>(`/roadside-requests/${id}`),

  /** Cerere scrisă — fără fișiere/foto (clientul nu încarcă nimic). */
  createRoadsideRequest: (data: {
    location: string;
    problem: string;
    mobility: string;
    safety: string;
    phone: string;
    vehicleId?: string;
  }) => request<RoadsideRequest>('/roadside-requests', { method: 'POST', body: JSON.stringify(data) }),

  cancelRoadsideRequest: (id: string) =>
    request<RoadsideRequest>(`/roadside-requests/${id}/cancel`, { method: 'POST' }),

  mobilityRequests: () => request<MobilityRequest[]>('/mobility-requests'),

  mobilityRequest: (id: string) => request<MobilityRequest>(`/mobility-requests/${id}`),

  createMobilityRequest: (data: { type: string; details: string; preferredDate?: string; vehicleId?: string }) =>
    request<MobilityRequest>('/mobility-requests', { method: 'POST', body: JSON.stringify(data) }),

  cancelMobilityRequest: (id: string) =>
    request<MobilityRequest>(`/mobility-requests/${id}/cancel`, { method: 'POST' }),

  // Dosarul de daună se deschide EXCLUSIV pe amiabila.com — fără API de client.

  taxes: () => request<TaxItem[]>('/taxes'),

  tax: (id: string) => request<TaxItem>(`/taxes/${id}`),

  createTax: (data: { year: number; type: string; amount: number; dueDate?: string; vehicleId?: string }) =>
    request<TaxItem>('/taxes', { method: 'POST', body: JSON.stringify(data) }),

  updateTax: (id: string, data: { year?: number; type?: string; amount?: number; dueDate?: string; vehicleId?: string }) =>
    request<TaxItem>(`/taxes/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),

  deleteTax: (id: string) => request<void>(`/taxes/${id}`, { method: 'DELETE' }),

  /** Fără sumă → plată integrală; cu sumă (RON) → plată parțială/cumulativă. Fără fișiere. */
  payTax: (id: string, amount?: number) =>
    request<TaxItem>(`/taxes/${id}/pay`, {
      method: 'POST',
      body: JSON.stringify(amount !== undefined ? { amount } : {}),
    }),
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
    throw new ApiError(
      { type: 'network_error', title: 'Conexiune indisponibilă. Verificați internetul.', status: 0, traceId: '' },
      0,
    );
  }

  const parsed = (await res.json().catch(() => null)) as unknown;
  if (!res.ok) {
    const problem = (parsed as ApiProblem | null) ?? {
      type: 'error',
      title: 'Încărcare eșuată.',
      status: res.status,
      traceId: '',
    };
    throw new ApiError(problem, res.status);
  }

  return parsed as T;
}
