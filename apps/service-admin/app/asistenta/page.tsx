'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type RoadsideRequest, type RoadsideStatus } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

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
  const t = useT();
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
      <header className="page-head">
        <div>
          <h1>{t('Asistență rutieră')}</h1>
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
      {!error && items?.length === 0 ? <EmptyState title={t('Nicio cerere de asistență')} /> : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((r) => (
              <Link key={r.id} href={`/asistenta/${r.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{r.location}</span>
                  <span className="row-sub">
                    {r.customerName ?? '—'} · {t(r.mobilityLabel)} · {r.phone}
                  </span>
                </span>
                <span className={`badge ${STATUS_CLASS[r.status]}`}>{t(r.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
