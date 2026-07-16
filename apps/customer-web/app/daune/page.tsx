'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type DamageClaim, type DamageClaimStatus } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<DamageClaimStatus, string> = {
  NEW: 'badge-warn',
  IN_PROGRESS: 'badge-unknown',
  CLOSED: 'badge-ok',
  CANCELLED: 'badge-err',
};

export default function DamageClaimListPage() {
  const router = useRouter();
  const [items, setItems] = useState<DamageClaim[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .damageClaims()
      .then(setItems)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
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
        <h1>Dosar de daună</h1>
        <Link className="btn" style={{ width: 'auto', padding: '8px 12px' }} href="/daune/nou">
          + Dosar
        </Link>
      </div>

      {error ? <ErrorState message={error} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title="Niciun dosar" hint="Deschideți un dosar de daună cu ajutorul service-ului." />
      ) : null}

      {items && items.length > 0 ? (
        <div className="stack" style={{ gap: 10 }}>
          {items.map((c) => (
            <Link key={c.id} href={`/daune/${c.id}`} className="card" style={{ textDecoration: 'none', color: 'inherit' }}>
              <div className="list-row">
                <div>
                  <strong>{c.insurer ?? 'Dosar de daună'}</strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {c.incidentDate ?? new Date(c.createdAt).toLocaleDateString('ro-RO')}
                    {c.policyNumber ? ` · ${c.policyNumber}` : ''}
                  </div>
                </div>
                <span className={`badge ${STATUS_CLASS[c.status]}`}>{c.statusLabel}</span>
              </div>
            </Link>
          ))}
        </div>
      ) : null}

      <BottomNav />
    </>
  );
}
