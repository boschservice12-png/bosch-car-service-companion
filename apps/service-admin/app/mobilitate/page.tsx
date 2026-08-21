'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type MobilityRequest, type MobilityStatus } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

const STATUS_CLASS: Record<MobilityStatus, string> = {
  SUBMITTED: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  CONTACTED: 'badge-unknown',
  CONFIRMED: 'badge-ok',
  UNAVAILABLE: 'badge-err',
  COMPLETED: 'badge-ok',
  CANCELLED: 'badge-err',
};

export default function AdminMobilityListPage() {
  const router = useRouter();
  const t = useT();
  const [items, setItems] = useState<MobilityRequest[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .mobilityRequests()
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
          <h1>{t('Mobilitate')}</h1>
        </div>
        <div className="page-head-actions">
          <Link href="/" className="back-link">
            <Icon name="arrow-left" size={14} />
          {t('Vehicule')}
          </Link>
        </div>
      </header>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? <EmptyState title={t('Nicio solicitare de mobilitate')} /> : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((m) => (
              <Link key={m.id} href={`/mobilitate/${m.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{t(m.typeLabel)}</span>
                  <span className="row-sub">
                    {m.customerName ?? '—'}
                    {m.preferredDate ? t(' · pentru {date}', { date: m.preferredDate }) : ''}
                  </span>
                </span>
                <span className={`badge ${STATUS_CLASS[m.status]}`}>{t(m.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
