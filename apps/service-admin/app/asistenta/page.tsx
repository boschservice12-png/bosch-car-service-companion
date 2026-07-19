'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type RoadsideRequest, type RoadsideStatus } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<RoadsideStatus, string> = {
  SUBMITTED: 'badge-warn',
  VALIDATED: 'badge-unknown',
  FORWARDED: 'badge-unknown',
  IN_PROGRESS: 'badge-unknown',
  COMPLETED: 'badge-ok',
  CANCELLED: 'badge-err',
};

export default function AdminRoadsideListPage() {
  const router = useRouter();
  const [items, setItems] = useState<RoadsideRequest[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .roadsideRequests()
      .then(setItems)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [router]);

  useEffect(load, [load]);

  return (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>Asistență rutieră</h1>
        <Link href="/" className="muted">
          Vehicule →
        </Link>
      </div>

      {error ? <ErrorState message={error} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? <EmptyState title="Nicio cerere de asistență" /> : null}

      {items && items.length > 0 ? (
        <div className="stack" style={{ gap: 10 }}>
          {items.map((r) => (
            <Link key={r.id} href={`/asistenta/${r.id}`} className="card" style={{ textDecoration: 'none', color: 'inherit' }}>
              <div className="list-row">
                <div>
                  <strong>{r.location}</strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {r.customerName ?? '—'} · {r.mobilityLabel} · {r.phone}
                  </div>
                </div>
                <span className={`badge ${STATUS_CLASS[r.status]}`}>{r.statusLabel}</span>
              </div>
            </Link>
          ))}
        </div>
      ) : null}
    </>
  );
}
