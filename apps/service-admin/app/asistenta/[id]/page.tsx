'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, roadsideDocumentHref } from '@/lib/api';
import { ApiError, type RoadsideRequest, type RoadsideStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<RoadsideStatus, string> = {
  NEW: 'badge-warn',
  FORWARDED: 'badge-unknown',
  RESOLVED: 'badge-ok',
  CANCELLED: 'badge-err',
};

const ACTIONS: { status: string; label: string }[] = [
  { status: 'FORWARDED', label: 'Preia (contact telefonic)' },
  { status: 'RESOLVED', label: 'Marchează rezolvată' },
  { status: 'CANCELLED', label: 'Anulează' },
];

export default function AdminRoadsideDetailPage() {
  const router = useRouter();
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

  if (error && req === null) return <ErrorState message={error} onRetry={load} />;
  if (req === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/asistenta" className="muted">
        ← Asistență rutieră
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ marginBottom: 0 }}>{req.customerName ?? 'Client'}</h1>
        <span className={`badge ${STATUS_CLASS[req.status]}`}>{req.statusLabel}</span>
      </div>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        <div><span className="muted">Locație:</span> {req.location}</div>
        <div><span className="muted">Problemă:</span> {req.problem}</div>
        <div><span className="muted">Mobilitate:</span> {req.mobilityLabel}</div>
        <div><span className="muted">Siguranță:</span> {req.safetyLabel}</div>
        <div><strong>Telefon: <a href={`tel:${req.phone}`}>{req.phone}</a></strong></div>
        {req.vehiclePlate ? <div><span className="muted">Vehicul:</span> {req.vehiclePlate}</div> : null}
        {req.note ? <div><span className="muted">Notă:</span> {req.note}</div> : null}
      </div>

      {req.documents.length > 0 ? (
        <div className="card stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: '0.82rem' }}>Documente:</span>
          {req.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <span aria-hidden>📎</span>
              <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{d.originalName ?? 'document'}</span>
              {d.servable ? (
                <a className="btn btn-ghost" style={{ width: 'auto', padding: '4px 10px' }} href={roadsideDocumentHref(req.id, d.id)} target="_blank" rel="noopener">
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
