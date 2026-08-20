'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type RoadsideRequest, type RoadsideStatus } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

/**
 * Două linii telefonice, după situația clientului: cu asistență rutieră
 * activă (linia NON-STOP) sau fără (dispeceratul service-ului).
 * Numerele sunt configurabile la nivel de service.
 */
const CALL_LINES = [
  {
    key: 'activ',
    label: 'Am asistență rutieră activă',
    name: 'Linia de asistență NON-STOP',
    phone: '0372 500 000',
    note: 'NON-STOP, 24/7 — pentru clienții cu asistență rutieră activă.',
  },
  {
    key: 'fara',
    label: 'Fără asistență rutieră',
    name: 'Dispeceratul service-ului',
    phone: '0730 508 343',
    note: 'Intervenția se facturează separat.',
  },
] as const;

const STATUS_CLASS: Record<RoadsideStatus, string> = {
  SUBMITTED: 'badge-warn',
  VALIDATED: 'badge-unknown',
  FORWARDED: 'badge-unknown',
  IN_PROGRESS: 'badge-unknown',
  COMPLETED: 'badge-ok',
  CANCELLED: 'badge-err',
};

export default function RoadsideListPage() {
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
          <h1>{t('Asistență rutieră')}</h1>
        </div>
        <div className="page-head-actions">
          <Link className="btn btn-sm" href="/asistenta/nou">
            {t('+ Cerere')}
          </Link>
        </div>
      </header>

      <div className="panel panel-body panel-form stack">
        <strong>{t('Solicită asistență — alege situația ta')}</strong>
        {CALL_LINES.map((line) => (
          <a
            key={line.key}
            className={line.key === 'activ' ? 'btn' : 'btn btn-ghost'}
            href={`tel:${line.phone.replace(/\s/g, '')}`}
            style={{ textAlign: 'center', textDecoration: 'none' }}
          >
            {t(line.label)} — {line.phone}
          </a>
        ))}
        <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>
          {t(CALL_LINES[0].name)}: {t(CALL_LINES[0].note)} {t(CALL_LINES[1].name)}: {t(CALL_LINES[1].note)}
        </span>
      </div>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title={t('Nicio cerere')} hint={t('Deschideți o cerere de asistență dacă aveți nevoie de ajutor pe drum.')} />
      ) : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((r) => (
              <Link key={r.id} href={`/asistenta/${r.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{r.location}</span>
                  <span className="row-sub">
                    {t(r.mobilityLabel)} · {new Date(r.createdAt).toLocaleDateString('ro-RO')}
                  </span>
                </span>
                <span className={`badge ${STATUS_CLASS[r.status]}`}>{t(r.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      <BottomNav />
    </>
  );
}
