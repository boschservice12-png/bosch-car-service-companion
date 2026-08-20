'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type MobilityRequest, type MobilityStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
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

const ACTIONS: { status: string; label: string }[] = [
  { status: 'IN_REVIEW', label: 'Preia în analiză' },
  { status: 'CONTACTED', label: 'Client contactat' },
  { status: 'CONFIRMED', label: 'Confirmă' },
  { status: 'UNAVAILABLE', label: 'Indisponibilă' },
  { status: 'COMPLETED', label: 'Finalizează' },
  { status: 'CANCELLED', label: 'Anulează' },
];

export default function AdminMobilityDetailPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [req, setReq] = useState<MobilityRequest | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .mobilityRequest(params.id)
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
      await api.updateMobilityStatus(params.id, { status, note: note || undefined });
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
      <div className="muted" style={{ fontSize: 'var(--text-sm)', marginBottom: 12 }}>{req.customerName ?? t('Client')}</div>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <div className="panel panel-body panel-form stack">
        <div><span className="muted">{t('Detalii:')}</span> {req.details}</div>
        {req.preferredDate ? <div><span className="muted">{t('Data preferată:')}</span> {req.preferredDate}</div> : null}
        {req.vehiclePlate ? <div><span className="muted">{t('Vehicul:')}</span> {req.vehiclePlate}</div> : null}
        {req.note ? <div><span className="muted">{t('Notă:')}</span> {req.note}</div> : null}
      </div>

      <h2>{t('Actualizează starea')}</h2>
      <div className="panel panel-body panel-form stack">
        <textarea
          value={note}
          onChange={(e) => setNote(e.target.value)}
          rows={2}
          placeholder={t('Notă internă (opțional)…')}
          style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: 'var(--text-md)', background: '#fff' }}
        />
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {ACTIONS.map((a) => (
            <button key={a.status} className="btn btn-ghost btn-sm" disabled={busy} onClick={() => setStatus(a.status)}>
              {t(a.label)}
            </button>
          ))}
        </div>
      </div>
    </>
  );
}
