'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type MobilityRequest, type MobilityStatus } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<MobilityStatus, string> = {
  SUBMITTED: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  CONTACTED: 'badge-unknown',
  CONFIRMED: 'badge-ok',
  UNAVAILABLE: 'badge-err',
  COMPLETED: 'badge-ok',
  CANCELLED: 'badge-err',
};

export default function MobilityListPage() {
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
      <header className="page-head">
        <div>
          <h1>{t('Mobilitate')}</h1>
        </div>
        <div className="page-head-actions">
          <Link className="btn btn-sm" href="/mobilitate/nou">
            {t('+ Cerere')}
          </Link>
        </div>
      </header>

      <div className="panel panel-body panel-form stack">
        <strong>{t('Aveți nevoie de mobilitate acum?')}</strong>
        <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>
          {t('Trimiteți o cerere, sau sunați direct dispeceratul.')}
        </span>
        <a className="btn" href="tel:0730508343" style={{ textAlign: 'center', textDecoration: 'none' }}>
          <Icon name="phone" size={16} /> {t('Sună {phone}', { phone: '0730 508 343' })}
        </a>
      </div>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title={t('Nicio solicitare')} hint={t('Cereți o mașină de înlocuire, un taxi sau transport acasă.')} />
      ) : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((m) => (
              <Link key={m.id} href={`/mobilitate/${m.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{t(m.typeLabel)}</span>
                  <span className="row-sub">
                    {m.preferredDate ? t('Pentru {date}', { date: m.preferredDate }) : new Date(m.createdAt).toLocaleDateString('ro-RO')}
                  </span>
                </span>
                <span className={`badge ${STATUS_CLASS[m.status]}`}>{t(m.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      <BottomNav />
    </>
  );
}
