import { ApiError, type AdminVehicle, type ApiProblem, type Deadline, type Me } from './types';

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
};
