import { ApiError, type AdminVehicle, type ApiProblem, type Deadline, type Me } from './types';

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
