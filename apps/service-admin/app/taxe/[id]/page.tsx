'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type TaxItem, type PaymentStatus } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

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
  const t = useT();
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

  if (error && tax === null) return <ErrorState message={t(error)} onRetry={load} />;
  if (tax === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/taxe" className="muted">
        {t('← Taxe și impozite')}
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1 style={{ marginBottom: 0 }}>{t(tax.typeLabel)} · {tax.year}</h1>
        <span className={`badge ${STATUS_CLASS[tax.status]}`}>{t(tax.statusLabel)}</span>
      </div>
      <div className="muted" style={{ fontSize: '0.85rem', marginBottom: 12 }}>{tax.customerName ?? t('Client')}</div>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <div className="card stack" style={{ gap: 8 }}>
        <div><strong>{money(tax.amount)}</strong></div>
        {tax.dueDate ? <div><span className="muted">{t('Scadență:')}</span> {tax.dueDate}</div> : null}
        {tax.vehiclePlate ? <div><span className="muted">{t('Vehicul:')}</span> {tax.vehiclePlate}</div> : null}
        {tax.note ? <div><span className="muted">{t('Notă:')}</span> {tax.note}</div> : null}
      </div>

      <h2>{t('Stare de plată')}</h2>
      <div className="card stack" style={{ gap: 10 }}>
        <textarea
          value={note}
          onChange={(e) => setNote(e.target.value)}
          rows={2}
          placeholder={t('Notă internă (opțional)…')}
          style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
        />
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} disabled={busy} onClick={() => setStatus('PAID')}>
            {t('Marchează plătită')}
          </button>
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} disabled={busy} onClick={() => setStatus('UNPAID')}>
            {t('Marchează neplătită')}
          </button>
        </div>
      </div>
    </>
  );
}
