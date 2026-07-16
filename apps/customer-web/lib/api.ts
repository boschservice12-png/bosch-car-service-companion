import { ApiError, type ApiProblem, type Deadline, type Me, type ServiceRecord, type Vehicle } from './types';

/** URL (same-origin, proxy /api) pentru descărcarea unui document de service. */
export function serviceRecordDocumentHref(recordId: string, documentId: string): string {
  return `/api/service-records/${recordId}/documents/${documentId}`;
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
