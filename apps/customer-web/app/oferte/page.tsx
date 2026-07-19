'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type QuoteRequest, type QuoteRequestStatus } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<QuoteRequestStatus, string> = {
  DRAFT: 'badge-unknown',
  SUBMITTED: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  NEEDS_INFORMATION: 'badge-warn',
  REPLIED: 'badge-ok',
  ACCEPTED: 'badge-ok',
  DECLINED: 'badge-err',
  CLOSED: 'badge-unknown',
};

export default function QuoteRequestListPage() {
  const router = useRouter();
  const [items, setItems] = useState<QuoteRequest[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .quoteRequests()
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
        <h1>Cere ofertă</h1>
        <Link className="btn" style={{ width: 'auto', padding: '8px 12px' }} href="/oferte/nou">
          + Cerere
        </Link>
      </div>

      {error ? <ErrorState message={error} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title="Nicio cerere de ofertă" hint="Descrieți problema și primiți o estimare de preț de la service." />
      ) : null}

      {items && items.length > 0 ? (
        <div className="stack" style={{ gap: 10 }}>
          {items.map((q) => (
            <Link key={q.id} href={`/oferte/${q.id}`} className="card" style={{ textDecoration: 'none', color: 'inherit' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, alignItems: 'center' }}>
                <div>
                  <strong style={{ display: 'block' }}>
                    {q.symptomDescription.length > 60 ? `${q.symptomDescription.slice(0, 60)}…` : q.symptomDescription}
                  </strong>
                  <span className="muted" style={{ fontSize: '0.82rem' }}>
                    {q.vehiclePlate ? `${q.vehiclePlate} · ` : ''}
                    {new Date(q.createdAt).toLocaleDateString('ro-RO')}
                  </span>
                </div>
                <span className={`badge ${STATUS_CLASS[q.status]}`}>{q.statusLabel}</span>
              </div>
            </Link>
          ))}
        </div>
      ) : null}

      <BottomNav />
    </>
  );
}
