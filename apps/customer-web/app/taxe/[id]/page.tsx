'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, taxDocumentHref } from '@/lib/api';
import { ApiError, type TaxItem, type PaymentStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { AttachmentPicker, type PickedFile } from '@/components/AttachmentPicker';

const STATUS_CLASS: Record<PaymentStatus, string> = {
  UNPAID: 'badge-warn',
  PAID: 'badge-ok',
};

function money(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

export default function TaxDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [tax, setTax] = useState<TaxItem | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [receipt, setReceipt] = useState<PickedFile[]>([]);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .tax(params.id)
      .then(setTax)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        if (err instanceof ApiError && (err.httpStatus === 403 || err.httpStatus === 404)) {
          setError('Taxa nu este disponibilă.');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  async function pay() {
    setBusy(true);
    try {
      await api.payTax(params.id, receipt.map((r) => r.id));
      setReceipt([]);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Operațiune eșuată.');
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

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        <div><strong>{money(tax.amount)}</strong></div>
        {tax.dueDate ? <div><span className="muted">Scadență:</span> {tax.dueDate}</div> : null}
        {tax.vehiclePlate ? <div><span className="muted">Vehicul:</span> {tax.vehiclePlate}</div> : null}
        {tax.paidAt ? <div><span className="muted">Plătită la:</span> {new Date(tax.paidAt).toLocaleDateString('ro-RO')}</div> : null}
        {tax.note ? <div><span className="muted">Notă service:</span> {tax.note}</div> : null}
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

      {tax.status === 'UNPAID' ? (
        <div className="card stack" style={{ gap: 10 }}>
          <strong>Marchează plata</strong>
          <span className="muted" style={{ fontSize: '0.85rem' }}>Puteți atașa bizonjatul (opțional).</span>
          <AttachmentPicker files={receipt} onChange={setReceipt} />
          <button className="btn" disabled={busy} onClick={pay}>
            {busy ? '…' : 'Marchează plătită'}
          </button>
        </div>
      ) : null}
    </>
  );
}
