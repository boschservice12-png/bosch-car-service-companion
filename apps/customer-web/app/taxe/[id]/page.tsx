'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type TaxItem, type PaymentStatus, type Vehicle } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<PaymentStatus, string> = {
  UNPAID: 'badge-warn',
  PARTIALLY_PAID: 'badge-warn',
  PAID: 'badge-ok',
  OVERDUE: 'badge-err',
};

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: 12,
  border: '1px solid var(--border)',
  borderRadius: 8,
  fontSize: '1rem',
  background: '#fff',
};

function money(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

export default function TaxDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [tax, setTax] = useState<TaxItem | null>(null);
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  // Formular de editare (precompletat din taxă).
  const [editing, setEditing] = useState(false);
  const [year, setYear] = useState('');
  const [type, setType] = useState('VEHICLE_TAX');
  const [amount, setAmount] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [vehicleId, setVehicleId] = useState('');

  // Plată: goliți suma → plată integrală; cu sumă → plată parțială.
  const [payAmount, setPayAmount] = useState('');

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
  useEffect(() => {
    api.vehicles().then(setVehicles).catch(() => setVehicles([]));
  }, []);

  function startEdit(t: TaxItem) {
    setYear(String(t.year));
    setType(t.type);
    setAmount(String(t.amount));
    setDueDate(t.dueDate ?? '');
    setVehicleId(t.vehicleId ?? '');
    setEditing(true);
  }

  async function saveEdit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const updated = await api.updateTax(params.id, {
        year: Number.parseInt(year, 10),
        type,
        amount: Number.parseFloat(amount),
        dueDate, // '' = fără scadență
        vehicleId, // '' = fără vehicul
      });
      setTax(updated);
      setEditing(false);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Salvare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  async function pay() {
    setBusy(true);
    setError(null);
    try {
      const parsed = Number.parseFloat(payAmount);
      const updated = await api.payTax(params.id, payAmount !== '' && Number.isFinite(parsed) ? parsed : undefined);
      setPayAmount('');
      setTax(updated);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Operațiune eșuată.');
    } finally {
      setBusy(false);
    }
  }

  async function remove() {
    if (!window.confirm('Ștergeți această taxă din evidență?')) return;
    setBusy(true);
    setError(null);
    try {
      await api.deleteTax(params.id);
      router.replace('/taxe');
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Ștergere eșuată.');
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
        {tax.paidAmount !== null && tax.status !== 'PAID' ? (
          <div><span className="muted">Plătit până acum:</span> {money(tax.paidAmount)}</div>
        ) : null}
        {tax.dueDate ? <div><span className="muted">Scadență:</span> {tax.dueDate}</div> : null}
        {tax.vehiclePlate ? <div><span className="muted">Vehicul:</span> {tax.vehiclePlate}</div> : null}
        {tax.paidAt ? <div><span className="muted">Plătită la:</span> {new Date(tax.paidAt).toLocaleDateString('ro-RO')}</div> : null}
        {tax.note ? <div><span className="muted">Notă service:</span> {tax.note}</div> : null}
      </div>

      {tax.status === 'PAID' ? (
        <p className="muted" style={{ fontSize: '0.85rem' }}>
          Taxa este plătită integral. Pentru corecții contactați service-ul.
        </p>
      ) : editing ? (
        <form onSubmit={saveEdit} className="card stack" style={{ gap: 10 }}>
          <strong>Editează taxa</strong>
          <div className="field">
            <label htmlFor="year">An</label>
            <input id="year" type="number" min={2000} max={2100} value={year} onChange={(e) => setYear(e.target.value)} required />
          </div>
          <div className="field">
            <label htmlFor="type">Tip</label>
            <select id="type" value={type} onChange={(e) => setType(e.target.value)} style={inputStyle}>
              <option value="VEHICLE_TAX">Impozit auto</option>
              <option value="ENVIRONMENT">Taxă de mediu</option>
              <option value="OTHER">Altă taxă</option>
            </select>
          </div>
          <div className="field">
            <label htmlFor="amount">Sumă (RON)</label>
            <input id="amount" type="number" min={0} step="0.01" inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} required />
          </div>
          <div className="field">
            <label htmlFor="dueDate">Scadență (opțional)</label>
            <input id="dueDate" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
          </div>
          <div className="field">
            <label htmlFor="vehicle">Vehicul (opțional)</label>
            <select id="vehicle" value={vehicleId} onChange={(e) => setVehicleId(e.target.value)} style={inputStyle}>
              <option value="">— fără vehicul —</option>
              {vehicles.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.plateNumber}
                </option>
              ))}
            </select>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn" type="submit" disabled={busy}>
              {busy ? 'Se salvează…' : 'Salvează'}
            </button>
            <button className="btn btn-ghost" type="button" disabled={busy} onClick={() => setEditing(false)}>
              Renunță
            </button>
          </div>
        </form>
      ) : (
        <>
          <div className="card stack" style={{ gap: 10 }}>
            <strong>Marchează plata</strong>
            <span className="muted" style={{ fontSize: '0.85rem' }}>
              Evidență declarativă — nu se încarcă niciun document. Goliți suma pentru plată integrală.
            </span>
            <div className="field">
              <label htmlFor="payAmount">Sumă plătită (RON, opțional — pentru plată parțială)</label>
              <input
                id="payAmount"
                type="number"
                min={0}
                step="0.01"
                inputMode="decimal"
                value={payAmount}
                onChange={(e) => setPayAmount(e.target.value)}
              />
            </div>
            <button className="btn" disabled={busy} onClick={pay}>
              {busy ? '…' : payAmount !== '' ? 'Înregistrează plata parțială' : 'Marchează plătită integral'}
            </button>
          </div>

          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 14px' }} disabled={busy} onClick={() => startEdit(tax)}>
              ✏️ Editează
            </button>
            <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 14px', color: 'var(--err, #b3261e)' }} disabled={busy} onClick={remove}>
              🗑 Șterge
            </button>
          </div>
        </>
      )}
    </>
  );
}
