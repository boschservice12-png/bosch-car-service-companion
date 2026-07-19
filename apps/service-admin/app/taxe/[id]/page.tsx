'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, taxDocumentHref } from '@/lib/api';
import { ApiError, type TaxItem, type PaymentStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<PaymentStatus, string> = {
  UNPAID: 'badge-warn',
  PARTIALLY_PAID: 'badge-warn',
  PAID: 'badge-ok',
  OVERDUE: 'badge-err',
};

function money(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

export default function AdminTaxDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [tax, setTax] = useState<TaxItem | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .tax(params.id)
      .then(setTax)
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
      await api.updateTaxStatus(params.id, { status, note: note || undefined });
      setNote('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Actualizare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && tax === null) return <ErrorState message={error} onRetry={load} />;
  if (tax === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/taxe" className="muted">
        ← Taxe și impozite
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ marginBottom: 0 }}>{tax.typeLabel} · {tax.year}</h1>
        <span className={`badge ${STATUS_CLASS[tax.status]}`}>{tax.statusLabel}</span>
      </div>
      <div className="muted" style={{ fontSize: '0.85rem', marginBottom: 12 }}>{tax.customerName ?? 'Client'}</div>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        <div><strong>{money(tax.amount)}</strong></div>
        {tax.dueDate ? <div><span className="muted">Scadență:</span> {tax.dueDate}</div> : null}
        {tax.vehiclePlate ? <div><span className="muted">Vehicul:</span> {tax.vehiclePlate}</div> : null}
        {tax.note ? <div><span className="muted">Notă:</span> {tax.note}</div> : null}
      </div>

      {tax.documents.length > 0 ? (
        <div className="card stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: '0.82rem' }}>Bizonjate:</span>
          {tax.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <span aria-hidden>🧾</span>
              <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{d.originalName ?? 'bizonjat'}</span>
              {d.servable ? (
                <a className="btn btn-ghost" style={{ width: 'auto', padding: '4px 10px' }} href={taxDocumentHref(tax.id, d.id)} target="_blank" rel="noopener">
                  Descarcă
                </a>
              ) : (
                <span className="muted" style={{ fontSize: '0.8rem' }}>în curs de scanare</span>
              )}
            </div>
          ))}
        </div>
      ) : null}

      <h2>Stare de plată</h2>
      <div className="card stack" style={{ gap: 10 }}>
        <textarea
          value={note}
          onChange={(e) => setNote(e.target.value)}
          rows={2}
          placeholder="Notă internă (opțional)…"
          style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
        />
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} disabled={busy} onClick={() => setStatus('PAID')}>
            Marchează plătită
          </button>
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} disabled={busy} onClick={() => setStatus('UNPAID')}>
            Marchează neplătită
          </button>
        </div>
      </div>
    </>
  );
}
