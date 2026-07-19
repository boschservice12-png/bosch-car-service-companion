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

export default function NewConversationPage() {
  const router = useRouter();
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
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
      const conv = await api.startConversation({
        subject,
        body,
        vehicleId: vehicleId || undefined,
      });
      router.replace(`/mesaje/${conv.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <Link href="/mesaje" className="muted">
        ← Mesaje
      </Link>
      <h1>Mesaj nou</h1>
      {error ? (
        <div className="alert alert-err" role="alert">
          {error}
        </div>
      ) : null}
      <form onSubmit={submit} className="card stack" style={{ gap: 10 }}>
        <div className="field">
          <label htmlFor="subject">Subiect</label>
          <input id="subject" type="text" maxLength={200} value={subject} onChange={(e) => setSubject(e.target.value)} required />
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
        <div className="field">
          <label htmlFor="body">Mesaj</label>
          <textarea id="body" rows={4} value={body} onChange={(e) => setBody(e.target.value)} required style={inputStyle} />
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se trimite…' : 'Trimite'}
        </button>
      </form>
    </>
  );
}
