'use client';

import Link from 'next/link';
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
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>{t('Mobilitate')}</h1>
        <Link className="btn" style={{ width: 'auto', padding: '8px 12px' }} href="/mobilitate/nou">
          {t('+ Cerere')}
        </Link>
      </div>

      <div className="card stack" style={{ gap: 8 }}>
        <strong>{t('Aveți nevoie de mobilitate acum?')}</strong>
        <span className="muted" style={{ fontSize: '0.85rem' }}>
          {t('Trimiteți o cerere, sau sunați direct dispeceratul.')}
        </span>
        <a className="btn" href="tel:0730508343" style={{ textAlign: 'center', textDecoration: 'none' }}>
          {t('📞 Sună {phone}', { phone: '0730 508 343' })}
        </a>
      </div>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title={t('Nicio solicitare')} hint={t('Cereți o mașină de înlocuire, un taxi sau transport acasă.')} />
      ) : null}

      {items && items.length > 0 ? (
        <div className="stack" style={{ gap: 10 }}>
          {items.map((m) => (
            <Link key={m.id} href={`/mobilitate/${m.id}`} className="card" style={{ textDecoration: 'none', color: 'inherit' }}>
              <div className="list-row">
                <div>
                  <strong>{t(m.typeLabel)}</strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {m.preferredDate ? t('Pentru {date}', { date: m.preferredDate }) : new Date(m.createdAt).toLocaleDateString('ro-RO')}
                  </div>
                </div>
                <span className={`badge ${STATUS_CLASS[m.status]}`}>{t(m.statusLabel)}</span>
              </div>
            </Link>
          ))}
        </div>
      ) : null}

      <BottomNav />
    </>
  );
}
