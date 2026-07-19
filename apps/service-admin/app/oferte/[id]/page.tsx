'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type QuoteRequest, type QuoteRequestStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<QuoteRequestStatus, string> = {
  DRAFT: 'badge-unknown',
  SUBMITTED: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  NEEDS_INFORMATION: 'badge-warn',
  REPLIED: 'badge-ok',
  ACCEPTED: 'badge-ok',
  DECLINED: 'badge-err',
  CLOSED: 'badge-unknown',
};

const ACTIONS: { status: string; label: string }[] = [
  { status: 'IN_REVIEW', label: 'Preia în analiză' },
  { status: 'NEEDS_INFORMATION', label: 'Cere informații' },
  { status: 'CLOSED', label: 'Închide' },
];

export default function AdminQuoteRequestDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [req, setReq] = useState<QuoteRequest | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .quoteRequest(params.id)
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
    setError(null);
    try {
      setReq(await api.quoteRequestStatus(params.id, status));
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Tranziție nepermisă.');
    } finally {
      setBusy(false);
    }
  }

  async function respond(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      setReq(await api.respondQuoteRequest(params.id, { message }));
      setMessage('');
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimiterea răspunsului a eșuat.');
    } finally {
      setBusy(false);
    }
  }

  if (error && req === null) return <ErrorState message={error} onRetry={load} />;
  if (req === null) return <Loading rows={4} />;

  return (
    <>
      <Link href="/oferte" className="muted">
        ← Cereri ofertă
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 }}>
        <h1 style={{ marginBottom: 4 }}>Cerere de ofertă</h1>
        <span className={`badge ${STATUS_CLASS[req.status]}`}>{req.statusLabel}</span>
      </div>
      <div className="muted" style={{ fontSize: '0.85rem', marginBottom: 12 }}>
        {req.customerName ?? '—'}
        {req.vehiclePlate ? ` · ${req.vehiclePlate}` : ''} · {new Date(req.createdAt).toLocaleString('ro-RO')}
      </div>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="card stack" style={{ gap: 6 }}>
        {req.mileage != null ? <div><span className="muted">Kilometraj:</span> {req.mileage.toLocaleString('ro-RO')} km</div> : null}
        <div><span className="muted">Problemă:</span> {req.symptomDescription}</div>
        {req.occurrenceConditions ? <div><span className="muted">Când apare:</span> {req.occurrenceConditions}</div> : null}
        <div><span className="muted">Conducibilă:</span> {req.vehicleDrivable ? 'Da' : 'Nu'}</div>
        {req.warningLights ? <div><span className="muted">Martori:</span> {req.warningLights}</div> : null}
        {req.preferredContactMethod ? <div><span className="muted">Contact preferat:</span> {req.preferredContactMethod}</div> : null}
        {req.preferredInterval ? <div><span className="muted">Interval:</span> {req.preferredInterval}</div> : null}
      </div>

      {req.responses.length > 0 ? (
        <>
          <h2 style={{ marginTop: 16 }}>Răspunsuri trimise</h2>
          <div className="stack" style={{ gap: 10 }}>
            {req.responses.map((r) => (
              <div key={r.id} className="card">
                <div className="muted" style={{ fontSize: '0.78rem', marginBottom: 4 }}>
                  {new Date(r.createdAt).toLocaleString('ro-RO')}
                </div>
                <div style={{ whiteSpace: 'pre-wrap' }}>{r.message}</div>
              </div>
            ))}
          </div>
        </>
      ) : null}

      <h2 style={{ marginTop: 16 }}>Acțiuni</h2>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        {ACTIONS.map((a) => (
          <button key={a.status} className="btn btn-ghost" style={{ width: 'auto', padding: '8px 14px' }} disabled={busy} onClick={() => void setStatus(a.status)}>
            {a.label}
          </button>
        ))}
      </div>

      {req.status === 'IN_REVIEW' ? (
        <>
          <h2 style={{ marginTop: 16 }}>Trimite oferta</h2>
          <form onSubmit={respond} className="card stack" style={{ gap: 10 }}>
            <textarea
              value={message}
              onChange={(e) => setMessage(e.target.value)}
              rows={3}
              required
              placeholder="Estimare, piese, manoperă, termen…"
              style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
            />
            <button className="btn" type="submit" disabled={busy}>
              {busy ? 'Se trimite…' : 'Trimite oferta (REPLIED)'}
            </button>
          </form>
        </>
      ) : null}
    </>
  );
}
