'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type AdminVehicle } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';

export default function DashboardPage() {
  const router = useRouter();
  const [vehicles, setVehicles] = useState<AdminVehicle[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    setVehicles(null);
    api
      .adminVehicles()
      .then(setVehicles)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Nu am putut încărca vehiculele.');
      });
  }, [router]);

  useEffect(load, [load]);

  async function logout() {
    await api.logout().catch(() => undefined);
    router.replace('/login');
  }

  return (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>Vehicule</h1>
        <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} onClick={logout}>
          Ieșire
        </button>
      </div>

      {error ? <ErrorState message={error} onRetry={load} /> : null}
      {!error && vehicles === null ? <Loading /> : null}
      {!error && vehicles?.length === 0 ? <EmptyState title="Niciun vehicul înregistrat" /> : null}

      {vehicles && vehicles.length > 0 ? (
        <div className="card">
          {vehicles.map((v) => (
            <div key={v.id} className="list-row">
              <div>
                <strong>{v.plateNumber}</strong>
                <div className="muted" style={{ fontSize: '0.85rem' }}>
                  {[v.make, v.model, v.year].filter(Boolean).join(' ') || v.vin}
                  {v.ownerName ? ` · ${v.ownerName}` : ''}
                </div>
              </div>
              <Link href={`/vehicule/${v.id}`}>Scadențe →</Link>
            </div>
          ))}
        </div>
      ) : null}
    </>
  );
}
