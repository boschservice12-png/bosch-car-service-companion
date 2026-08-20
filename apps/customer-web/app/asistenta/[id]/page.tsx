'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, roadsideDocumentHref } from '@/lib/api';
import { ApiError, type RoadsideRequest, type RoadsideStatus } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<RoadsideStatus, string> = {
  SUBMITTED: 'badge-warn',
  VALIDATED: 'badge-unknown',
  FORWARDED: 'badge-unknown',
  IN_PROGRESS: 'badge-unknown',
  COMPLETED: 'badge-ok',
  CANCELLED: 'badge-err',
};

export default function RoadsideDetailPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [req, setReq] = useState<RoadsideRequest | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .roadsideRequest(params.id)
      .then(setReq)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        if (err instanceof ApiError && (err.httpStatus === 403 || err.httpStatus === 404)) {
          setError('Cererea nu este disponibilă.');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  async function cancel() {
    setBusy(true);
    try {
      await api.cancelRoadsideRequest(params.id);
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
  <Link href="/asistenta" className="back-link">
            <Icon name="arrow-left" size={14} />
            {t('Asistență rutieră')}
          </Link>
          <h1>{t('Cerere de asistență')}</h1>
        </div>
      </header>
        </div>
        <div className="page-head-actions">
          <span className={`badge ${STATUS_CLASS[req.status]}`}>{t(req.statusLabel)}</span>
        </div>
      </header>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <div className="panel panel-body panel-form stack">
        <div><span className="muted">{t('Locație:')}</span> {req.location}</div>
        <div><span className="muted">{t('Problemă:')}</span> {req.problem}</div>
        <div><span className="muted">{t('Mobilitate:')}</span> {t(req.mobilityLabel)}</div>
        <div><span className="muted">{t('Siguranță:')}</span> {t(req.safetyLabel)}</div>
        <div><span className="muted">{t('Telefon:')}</span> {req.phone}</div>
        {req.vehiclePlate ? <div><span className="muted">{t('Vehicul:')}</span> {req.vehiclePlate}</div> : null}
        {req.note ? <div><span className="muted">{t('Notă service:')}</span> {req.note}</div> : null}
      </div>

      {req.documents.length > 0 ? (
        <div className="card stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('Documente:')}</span>
          {req.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <Icon name="paperclip" size={14} />
              <span style={{ fontSize: 'var(--text-sm)', wordBreak: 'break-all' }}>{d.originalName ?? t('document')}</span>
              {d.servable ? (
                <a className="btn btn-ghost" style={{ width: 'auto', padding: '4px 10px' }} href={roadsideDocumentHref(req.id, d.id)} target="_blank" rel="noopener">
                  {t('Descarcă')}
                </a>
              ) : (
                <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('în curs de scanare')}</span>
              )}
            </div>
          ))}
        </div>
      ) : null}

      {req.status === 'SUBMITTED' ? (
        <button className="btn btn-ghost" disabled={busy} onClick={cancel}>
          {busy ? '…' : t('Anulează cererea')}
        </button>
      ) : null}
    </>
  );
}
