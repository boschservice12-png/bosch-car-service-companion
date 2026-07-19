'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type QuoteRequest, type QuoteRequestStatus } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
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

export default function QuoteRequestDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [req, setReq] = useState<QuoteRequest | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .quoteRequest(params.id)
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

  async function act(action: 'submit' | 'resubmit' | 'accept' | 'decline') {
    setBusy(true);
    setError(null);
    try {
      const fn = {
        submit: api.submitQuoteRequest,
        resubmit: api.resubmitQuoteRequest,
        accept: api.acceptQuoteRequest,
        decline: api.declineQuoteRequest,
      }[action];
      setReq(await fn(params.id));
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Operațiune eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && req === null) return <ErrorState message={error} onRetry={load} />;
  if (req === null) return <Loading rows={4} />;

  return (
    <>
      <Link href="/oferte" className="muted">
        ← Cereri de ofertă
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 }}>
        <h1 style={{ marginBottom: 4 }}>Cerere de ofertă</h1>
        <span className={`badge ${STATUS_CLASS[req.status]}`}>{req.statusLabel}</span>
      </div>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="card stack" style={{ gap: 6 }}>
        {req.vehiclePlate ? <div><span className="muted">Vehicul:</span> {req.vehiclePlate}</div> : null}
        {req.mileage != null ? <div><span className="muted">Kilometraj:</span> {req.mileage.toLocaleString('ro-RO')} km</div> : null}
        <div><span className="muted">Problemă:</span> {req.symptomDescription}</div>
        {req.occurrenceConditions ? <div><span className="muted">Când apare:</span> {req.occurrenceConditions}</div> : null}
        <div><span className="muted">Conducibilă:</span> {req.vehicleDrivable ? 'Da' : 'Nu'}</div>
        {req.warningLights ? <div><span className="muted">Martori:</span> {req.warningLights}</div> : null}
        {req.preferredInterval ? <div><span className="muted">Interval preferat:</span> {req.preferredInterval}</div> : null}
      </div>

      {req.responses.length > 0 ? (
        <>
          <h2 style={{ marginTop: 16 }}>Răspunsul service-ului</h2>
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

      {req.status === 'DRAFT' ? (
        <button className="btn" style={{ marginTop: 16 }} disabled={busy} onClick={() => void act('submit')}>
          Trimite cererea către service
        </button>
      ) : null}

      {req.status === 'NEEDS_INFORMATION' ? (
        <div className="card" style={{ marginTop: 16 }}>
          <p className="muted" style={{ marginBottom: 8 }}>
            Service-ul are nevoie de informații suplimentare. Contactați service-ul (telefon sau mesaj), apoi confirmați:
          </p>
          <button className="btn" disabled={busy} onClick={() => void act('resubmit')}>
            Am completat informațiile
          </button>
        </div>
      ) : null}

      {req.status === 'REPLIED' ? (
        <div style={{ display: 'flex', gap: 8, marginTop: 16 }}>
          <button className="btn" style={{ width: 'auto', padding: '10px 16px' }} disabled={busy} onClick={() => void act('accept')}>
            Acceptă oferta
          </button>
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '10px 16px' }} disabled={busy} onClick={() => void act('decline')}>
            Refuză
          </button>
        </div>
      ) : null}

      <BottomNav />
    </>
  );
}
