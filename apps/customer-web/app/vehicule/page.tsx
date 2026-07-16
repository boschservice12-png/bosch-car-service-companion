'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

export default function VehiclesPage() {
  const router = useRouter();
  const [vehicles, setVehicles] = useState<Vehicle[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    setVehicles(null);
    api
      .vehicles()
      .then(setVehicles)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Nu am putut încărca vehiculele.');
      });
  }, [router]);

  useEffect(load, [load]);

  return (
    <>
      <h1>Vehiculele mele</h1>

      {error ? <ErrorState message={error} onRetry={load} /> : null}

      {!error && vehicles === null ? <Loading /> : null}

      {!error && vehicles !== null && vehicles.length === 0 ? (
        <EmptyState
          title="Niciun vehicul încă"
          hint="Adăugați primul vehicul pentru a urmări scadențele și istoricul."
          action={
            <Link className="btn" href="/vehicule/nou">
              ➕ Adaugă vehicul
            </Link>
          }
        />
      ) : null}

      {!error && vehicles && vehicles.length > 0 ? (
        <>
          <div className="card">
            {vehicles.map((v) => (
              <div key={v.id} className="list-row">
                <div>
                  <strong>{v.plateNumber}</strong>
                  <div className="muted" style={{ fontSize: '0.85rem' }}>
                    {[v.make, v.model, v.year].filter(Boolean).join(' · ') || v.vin}
                  </div>
                </div>
                <Link href={`/vehicule/${v.id}`} aria-label={`Detalii ${v.plateNumber}`}>
                  Detalii →
                </Link>
              </div>
            ))}
          </div>
          <Link className="btn btn-ghost" href="/vehicule/nou">
            ➕ Adaugă alt vehicul
          </Link>
        </>
      ) : null}

      <BottomNav />
    </>
  );
}
