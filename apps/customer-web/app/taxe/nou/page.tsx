'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: 12,
  border: '1px solid var(--border)',
  borderRadius: 8,
  fontSize: '1rem',
  background: '#fff',
};

export default function NewTaxPage() {
  const router = useRouter();
  const [year, setYear] = useState(String(new Date().getFullYear()));
  const [type, setType] = useState('VEHICLE_TAX');
  const [amount, setAmount] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [vehicleId, setVehicleId] = useState('');
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.vehicles().then(setVehicles).catch(() => setVehicles([]));
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const tax = await api.createTax({
        year: Number.parseInt(year, 10),
        type,
        amount: Number.parseFloat(amount),
        dueDate: dueDate || undefined,
        vehicleId: vehicleId || undefined,
      });
      router.replace(`/taxe/${tax.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <Link href="/taxe" className="muted">
        ← Taxe și impozite
      </Link>
      <h1>Taxă nouă</h1>
      {error ? (
        <div className="alert alert-err" role="alert">
          {error}
        </div>
      ) : null}
      <form onSubmit={submit} className="card stack" style={{ gap: 10 }}>
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
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se salvează…' : 'Adaugă taxa'}
        </button>
      </form>
    </>
  );
}
