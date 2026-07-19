'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type DamageClaim, type DamageClaimStatus } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<DamageClaimStatus, string> = {
  SUBMITTED: 'badge-warn',
  DOCUMENTS_MISSING: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  CONTACTED: 'badge-unknown',
  FILE_OPENED: 'badge-ok',
  CLOSED: 'badge-ok',
};

export default function AdminDamageClaimListPage() {
  const router = useRouter();
  const [items, setItems] = useState<DamageClaim[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .damageClaims()
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
        <h1>Dosare de daună</h1>
        <Link href="/" className="muted">
          Vehicule →
        </Link>
      </div>

      {error ? <ErrorState message={error} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? <EmptyState title="Niciun dosar de daună" /> : null}

      {items && items.length > 0 ? (
        <div className="stack" style={{ gap: 10 }}>
          {items.map((c) => (
            <Link key={c.id} href={`/daune/${c.id}`} className="card" style={{ textDecoration: 'none', color: 'inherit' }}>
              <div className="list-row">
                <div>
                  <strong>{c.insurer ?? 'Dosar de daună'}</strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {c.customerName ?? '—'}
                    {c.policyNumber ? ` · ${c.policyNumber}` : ''}
                    {c.incidentDate ? ` · ${c.incidentDate}` : ''}
                  </div>
                </div>
                <span className={`badge ${STATUS_CLASS[c.status]}`}>{c.statusLabel}</span>
              </div>
            </Link>
          ))}
        </div>
      ) : null}
    </>
  );
}
