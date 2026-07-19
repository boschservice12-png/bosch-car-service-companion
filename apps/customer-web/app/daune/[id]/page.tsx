'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, damageClaimDocumentHref } from '@/lib/api';
import { ApiError, type DamageClaim, type DamageClaimStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<DamageClaimStatus, string> = {
  SUBMITTED: 'badge-warn',
  DOCUMENTS_MISSING: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  CONTACTED: 'badge-unknown',
  FILE_OPENED: 'badge-ok',
  CLOSED: 'badge-ok',
};

export default function DamageClaimDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [claim, setClaim] = useState<DamageClaim | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .damageClaim(params.id)
      .then(setClaim)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        if (err instanceof ApiError && (err.httpStatus === 403 || err.httpStatus === 404)) {
          setError('Dosarul nu este disponibil.');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  async function cancel() {
    setBusy(true);
    try {
      await api.cancelDamageClaim(params.id);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Anulare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && claim === null) return <ErrorState message={error} onRetry={load} />;
  if (claim === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/daune" className="muted">
        ← Dosar de daună
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ marginBottom: 0 }}>{claim.insurer ?? 'Dosar de daună'}</h1>
        <span className={`badge ${STATUS_CLASS[claim.status]}`}>{claim.statusLabel}</span>
      </div>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        {claim.incidentDate ? <div><span className="muted">Data:</span> {claim.incidentDate}</div> : null}
        {claim.incidentLocation ? <div><span className="muted">Loc:</span> {claim.incidentLocation}</div> : null}
        <div><span className="muted">Descriere:</span> {claim.incidentDescription}</div>
        {claim.policyNumber ? <div><span className="muted">Poliță:</span> {claim.policyNumber}</div> : null}
        {claim.vehiclePlate ? <div><span className="muted">Vehicul:</span> {claim.vehiclePlate}</div> : null}
        {claim.note ? <div><span className="muted">Notă service:</span> {claim.note}</div> : null}
      </div>

      {claim.documents.length > 0 ? (
        <div className="card stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: '0.82rem' }}>Fotografii și documente:</span>
          {claim.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <span aria-hidden>📎</span>
              <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{d.originalName ?? 'document'}</span>
              {d.servable ? (
                <a className="btn btn-ghost" style={{ width: 'auto', padding: '4px 10px' }} href={damageClaimDocumentHref(claim.id, d.id)} target="_blank" rel="noopener">
                  Descarcă
                </a>
              ) : (
                <span className="muted" style={{ fontSize: '0.8rem' }}>în curs de scanare</span>
              )}
            </div>
          ))}
        </div>
      ) : null}

      {claim.status === 'SUBMITTED' ? (
        <button className="btn btn-ghost" disabled={busy} onClick={cancel}>
          {busy ? '…' : 'Anulează dosarul'}
        </button>
      ) : null}
    </>
  );
}
