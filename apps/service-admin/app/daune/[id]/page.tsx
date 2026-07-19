'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, damageClaimDocumentHref } from '@/lib/api';
import { ApiError, type DamageClaim, type DamageClaimStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

const STATUS_CLASS: Record<DamageClaimStatus, string> = {
  SUBMITTED: 'badge-warn',
  DOCUMENTS_MISSING: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  CONTACTED: 'badge-unknown',
  FILE_OPENED: 'badge-ok',
  CLOSED: 'badge-ok',
};

const ACTIONS: { status: string; label: string }[] = [
  { status: 'DOCUMENTS_MISSING', label: 'Cere documente' },
  { status: 'IN_REVIEW', label: 'Preia în analiză' },
  { status: 'CONTACTED', label: 'Client contactat' },
  { status: 'FILE_OPENED', label: 'Dosar deschis' },
  { status: 'CLOSED', label: 'Închide' },
];

export default function AdminDamageClaimDetailPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [claim, setClaim] = useState<DamageClaim | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .damageClaim(params.id)
      .then(setClaim)
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
      await api.updateDamageClaimStatus(params.id, { status, note: note || undefined });
      setNote('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Actualizare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && claim === null) return <ErrorState message={t(error)} onRetry={load} />;
  if (claim === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/daune" className="muted">
        {t('← Dosare de daună')}
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ marginBottom: 0 }}>{claim.insurer ?? t('Dosar de daună')}</h1>
        <span className={`badge ${STATUS_CLASS[claim.status]}`}>{t(claim.statusLabel)}</span>
      </div>
      <div className="muted" style={{ fontSize: '0.85rem', marginBottom: 12 }}>{claim.customerName ?? t('Client')}</div>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        {claim.incidentDate ? <div><span className="muted">{t('Data:')}</span> {claim.incidentDate}</div> : null}
        {claim.incidentLocation ? <div><span className="muted">{t('Loc:')}</span> {claim.incidentLocation}</div> : null}
        <div><span className="muted">{t('Descriere:')}</span> {claim.incidentDescription}</div>
        {claim.policyNumber ? <div><span className="muted">{t('Poliță:')}</span> {claim.policyNumber}</div> : null}
        {claim.vehiclePlate ? <div><span className="muted">{t('Vehicul:')}</span> {claim.vehiclePlate}</div> : null}
        {claim.note ? <div><span className="muted">{t('Notă:')}</span> {claim.note}</div> : null}
      </div>

      {claim.documents.length > 0 ? (
        <div className="card stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: '0.82rem' }}>{t('Fotografii și documente:')}</span>
          {claim.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <span aria-hidden>📎</span>
              <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{d.originalName ?? t('document')}</span>
              {d.servable ? (
                <a className="btn btn-ghost" style={{ width: 'auto', padding: '4px 10px' }} href={damageClaimDocumentHref(claim.id, d.id)} target="_blank" rel="noopener">
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
