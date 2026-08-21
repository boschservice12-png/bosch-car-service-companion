'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type MobilityRequest, type MobilityStatus } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<MobilityStatus, string> = {
  SUBMITTED: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  CONTACTED: 'badge-unknown',
  CONFIRMED: 'badge-ok',
  UNAVAILABLE: 'badge-err',
  COMPLETED: 'badge-ok',
  CANCELLED: 'badge-err',
};

export default function MobilityDetailPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [req, setReq] = useState<MobilityRequest | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .mobilityRequest(params.id)
      .then(setReq)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        if (err instanceof ApiError && (err.httpStatus === 403 || err.httpStatus === 404)) {
          setError('Solicitarea nu este disponibilă.');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  async function cancel() {
    setBusy(true);
    try {
      await api.cancelMobilityRequest(params.id);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Anulare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && req === null) return <ErrorState message={t(error)} onRetry={load} />;
  if (req === null) return <Loading rows={3} />;

  return (
    <>
      <header className="page-head">
        <div>
      <header className="page-head">
        <div>
  <Link href="/mobilitate" className="back-link">
            <Icon name="arrow-left" size={14} />
            {t('Mobilitate')}
          </Link>
          <h1>{t(req.typeLabel)}</h1>
        </div>
      </header>
        </div>
        <div className="page-head-actions">
          <span className={`badge ${STATUS_CLASS[req.status]}`}>{t(req.statusLabel)}</span>
        </div>
      </header>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <div className="panel panel-body panel-form stack">
        <div><span className="muted">{t('Detalii:')}</span> {req.details}</div>
        {req.preferredDate ? <div><span className="muted">{t('Data preferată:')}</span> {req.preferredDate}</div> : null}
        {req.vehiclePlate ? <div><span className="muted">{t('Vehicul:')}</span> {req.vehiclePlate}</div> : null}
        {req.note ? <div><span className="muted">{t('Notă service:')}</span> {req.note}</div> : null}
      </div>

      {req.status === 'SUBMITTED' ? (
        <button className="btn btn-ghost" disabled={busy} onClick={cancel}>
          {busy ? '…' : t('Anulează solicitarea')}
        </button>
      ) : null}
    </>
  );
}
