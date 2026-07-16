'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, damageClaimDocumentHref } from '@/lib/api';
import { ApiError, type DamageClaim, type DamageClaimStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<DamageClaimStatus, string> = {
  NEW: 'badge-warn',
  IN_PROGRESS: 'badge-unknown',
  CLOSED: 'badge-ok',
  CANCELLED: 'badge-err',
};

const ACTIONS: { status: string; label: string }[] = [
  { status: 'IN_PROGRESS', label: 'Preia dosarul' },
  { status: 'CLOSED', label: 'Închide' },
  { status: 'CANCELLED', label: 'Anulează' },
];

export default function AdminDamageClaimDetailPage() {
  const router = useRouter();
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

  if (error && claim === null) return <ErrorState message={error} onRetry={load} />;
  if (claim === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/daune" className="muted">
        ← Dosare de daună
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ marginBottom: 0 }}>{claim.insurer ?? 'Dosar de daună'}</h1>
        <span className={`badge ${STATUS_CLASS[claim.status]}`}>{claim.statusLabel}</span>
      </div>
      <div className="muted" style={{ fontSize: '0.85rem', marginBottom: 12 }}>{claim.customerName ?? 'Client'}</div>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        {claim.incidentDate ? <div><span className="muted">Data:</span> {claim.incidentDate}</div> : null}
        {claim.incidentLocation ? <div><span className="muted">Loc:</span> {claim.incidentLocation}</div> : null}
        <div><span className="muted">Descriere:</span> {claim.incidentDescription}</div>
        {claim.policyNumber ? <div><span className="muted">Poliță:</span> {claim.policyNumber}</div> : null}
        {claim.vehiclePlate ? <div><span className="muted">Vehicul:</span> {claim.vehiclePlate}</div> : null}
        {claim.note ? <div><span className="muted">Notă:</span> {claim.note}</div> : null}
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

      <h2>Actualizează starea</h2>
      <div className="card stack" style={{ gap: 10 }}>
        <textarea
          value={note}
          onChange={(e) => setNote(e.target.value)}
          rows={2}
          placeholder="Notă internă (opțional)…"
          style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
        />
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {ACTIONS.map((a) => (
            <button key={a.status} className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} disabled={busy} onClick={() => setStatus(a.status)}>
              {a.label}
            </button>
          ))}
        </div>
      </div>
    </>
  );
}
