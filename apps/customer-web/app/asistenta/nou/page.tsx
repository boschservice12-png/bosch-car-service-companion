'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { AttachmentPicker, type PickedFile } from '@/components/AttachmentPicker';

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: 12,
  border: '1px solid var(--border)',
  borderRadius: 8,
  fontSize: '1rem',
  background: '#fff',
};

export default function NewRoadsidePage() {
  const router = useRouter();
  const [location, setLocation] = useState('');
  const [problem, setProblem] = useState('');
  const [mobility, setMobility] = useState('NOT_DRIVABLE');
  const [safety, setSafety] = useState('SAFE');
  const [phone, setPhone] = useState('');
  const [vehicleId, setVehicleId] = useState('');
  const [attachments, setAttachments] = useState<PickedFile[]>([]);
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
      const req = await api.createRoadsideRequest({
        location,
        problem,
        mobility,
        safety,
        phone,
        vehicleId: vehicleId || undefined,
        documentIds: attachments.map((a) => a.id),
      });
      router.replace(`/asistenta/${req.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <Link href="/asistenta" className="muted">
        ← Asistență rutieră
      </Link>
      <h1>Cerere de asistență</h1>
      {error ? (
        <div className="alert alert-err" role="alert">
          {error}
        </div>
      ) : null}
      <form onSubmit={submit} className="card stack" style={{ gap: 10 }}>
        <div className="field">
          <label htmlFor="location">Locația</label>
          <input id="location" type="text" maxLength={500} value={location} onChange={(e) => setLocation(e.target.value)} required placeholder="Ex: DN13, km 12" />
        </div>
        <div className="field">
          <label htmlFor="problem">Problema</label>
          <textarea id="problem" rows={3} value={problem} onChange={(e) => setProblem(e.target.value)} required style={inputStyle} />
        </div>
        <div className="field">
          <label htmlFor="mobility">Mașina se poate deplasa?</label>
          <select id="mobility" value={mobility} onChange={(e) => setMobility(e.target.value)} style={inputStyle}>
            <option value="DRIVABLE">Da, se poate deplasa</option>
            <option value="NOT_DRIVABLE">Nu, este imobilizată</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="safety">Sunteți în siguranță?</label>
          <select id="safety" value={safety} onChange={(e) => setSafety(e.target.value)} style={inputStyle}>
            <option value="SAFE">Da, în siguranță</option>
            <option value="AT_RISK">Nu, situație periculoasă</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="phone">Telefon de contact</label>
          <input id="phone" type="tel" maxLength={40} value={phone} onChange={(e) => setPhone(e.target.value)} required placeholder="+40…" />
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
        <AttachmentPicker files={attachments} onChange={setAttachments} />
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se trimite…' : 'Trimite cererea'}
        </button>
      </form>
    </>
  );
}
