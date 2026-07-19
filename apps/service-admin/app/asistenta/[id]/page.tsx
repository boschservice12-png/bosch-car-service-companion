'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, roadsideDocumentHref } from '@/lib/api';
import { ApiError, type RoadsideRequest, type RoadsideStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

const STATUS_CLASS: Record<RoadsideStatus, string> = {
  SUBMITTED: 'badge-warn',
  VALIDATED: 'badge-unknown',
  FORWARDED: 'badge-unknown',
  IN_PROGRESS: 'badge-unknown',
  COMPLETED: 'badge-ok',
  CANCELLED: 'badge-err',
};

const ACTIONS: { status: string; label: string }[] = [
  { status: 'VALIDATED', label: 'Validează' },
  { status: 'FORWARDED', label: 'Direcționează (contact telefonic)' },
  { status: 'IN_PROGRESS', label: 'În curs' },
  { status: 'COMPLETED', label: 'Finalizează' },
  { status: 'CANCELLED', label: 'Anulează' },
];

export default function AdminRoadsideDetailPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [req, setReq] = useState<RoadsideRequest | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .roadsideRequest(params.id)
      .then(setReq)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  async function setStatus(status: string) {
    setBusy(true);
    try {
      await api.updateRoadsideStatus(params.id, { status, note: note || undefined });
      setNote('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Actualizare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && req === null) return <ErrorState message={t(error)} onRetry={load} />;
  if (req === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/asistenta" className="muted">
        {t('← Asistență rutieră')}
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ marginBottom: 0 }}>{req.customerName ?? t('Client')}</h1>
        <span className={`badge ${STATUS_CLASS[req.status]}`}>{t(req.statusLabel)}</span>
      </div>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        <div><span className="muted">{t('Locație:')}</span> {req.location}</div>
        <div><span className="muted">{t('Problemă:')}</span> {req.problem}</div>
        <div><span className="muted">{t('Mobilitate:')}</span> {t(req.mobilityLabel)}</div>
        <div><span className="muted">{t('Siguranță:')}</span> {t(req.safetyLabel)}</div>
        <div><strong>{t('Telefon:')} <a href={`tel:${req.phone}`}>{req.phone}</a></strong></div>
        {req.vehiclePlate ? <div><span className="muted">{t('Vehicul:')}</span> {req.vehiclePlate}</div> : null}
        {req.note ? <div><span className="muted">{t('Notă:')}</span> {req.note}</div> : null}
      </div>

      {req.documents.length > 0 ? (
        <div className="card stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: '0.82rem' }}>{t('Documente:')}</span>
          {req.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <span aria-hidden>📎</span>
              <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{d.originalName ?? t('document')}</span>
              {d.servable ? (
                <a className="btn btn-ghost" style={{ width: 'auto', padding: '4px 10px' }} href={roadsideDocumentHref(req.id, d.id)} target="_blank" rel="noopener">
                  {t('Descarcă')}
                </a>
              ) : (
                <span className="muted" style={{ fontSize: '0.8rem' }}>{t('în curs de scanare')}</span>
              )}
            </div>
          ))}
        </div>
      ) : null}

      <h2>{t('Actualizează starea')}</h2>
      <div className="card stack" style={{ gap: 10 }}>
        <textarea
          value={note}
          onChange={(e) => setNote(e.target.value)}
          rows={2}
          placeholder={t('Notă internă (opțional)…')}
          style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
        />
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {ACTIONS.map((a) => (
            <button key={a.status} className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} disabled={busy} onClick={() => setStatus(a.status)}>
              {t(a.label)}
            </button>
          ))}
        </div>
      </div>
    </>
  );
}
