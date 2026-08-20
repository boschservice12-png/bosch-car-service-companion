'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type QuoteRequest, type QuoteRequestStatus } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

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

export default function AdminQuoteRequestsPage() {
  const router = useRouter();
  const t = useT();
  const [items, setItems] = useState<QuoteRequest[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .quoteRequests()
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
      <header className="page-head">
        <div>
          <h1>{t('Cereri ofertă')}</h1>
          <p className="muted">{t('Cererile de ofertă ale clienților (ciornele nu apar aici).')}</p>
        </div>
      </header>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={3} /> : null}
      {!error && items?.length === 0 ? <EmptyState title={t('Nicio cerere de ofertă')} hint={t('Cererile trimise de clienți apar aici.')} /> : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((q) => (
              <Link key={q.id} href={`/oferte/${q.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{q.symptomDescription.length > 70 ? `${q.symptomDescription.slice(0, 70)}…` : q.symptomDescription}</span>
                  <span className="row-sub">
                    {q.customerName ?? '—'}
                    {q.vehiclePlate ? ` · ${q.vehiclePlate}` : ''} · {new Date(q.createdAt).toLocaleDateString('ro-RO')}
                  </span>
                </span>
                <span className={`badge ${STATUS_CLASS[q.status]}`}>{t(q.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
