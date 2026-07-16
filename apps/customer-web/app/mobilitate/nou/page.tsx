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

export default function NewMobilityPage() {
  const router = useRouter();
  const [type, setType] = useState('REPLACEMENT_CAR');
  const [details, setDetails] = useState('');
  const [preferredDate, setPreferredDate] = useState('');
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
      const req = await api.createMobilityRequest({
        type,
        details,
        preferredDate: preferredDate || undefined,
        vehicleId: vehicleId || undefined,
      });
      router.replace(`/mobilitate/${req.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <Link href="/mobilitate" className="muted">
        ← Mobilitate
      </Link>
      <h1>Solicitare de mobilitate</h1>
      {error ? (
        <div className="alert alert-err" role="alert">
          {error}
        </div>
      ) : null}
      <form onSubmit={submit} className="card stack" style={{ gap: 10 }}>
        <div className="field">
          <label htmlFor="type">Tip</label>
          <select id="type" value={type} onChange={(e) => setType(e.target.value)} style={inputStyle}>
            <option value="REPLACEMENT_CAR">Mașină de înlocuire</option>
            <option value="TAXI">Taxi</option>
            <option value="RIDE_HOME">Transport acasă</option>
            <option value="OTHER">Altă solicitare</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="details">Detalii</label>
          <textarea id="details" rows={3} value={details} onChange={(e) => setDetails(e.target.value)} required style={inputStyle} />
        </div>
        <div className="field">
          <label htmlFor="preferredDate">Data preferată (opțional)</label>
          <input id="preferredDate" type="date" value={preferredDate} onChange={(e) => setPreferredDate(e.target.value)} />
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
          {busy ? 'Se trimite…' : 'Trimite solicitarea'}
        </button>
      </form>
    </>
  );
}
