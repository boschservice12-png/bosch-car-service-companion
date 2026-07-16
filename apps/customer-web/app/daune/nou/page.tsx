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

export default function NewDamageClaimPage() {
  const router = useRouter();
  const [incidentDate, setIncidentDate] = useState('');
  const [incidentLocation, setIncidentLocation] = useState('');
  const [incidentDescription, setIncidentDescription] = useState('');
  const [insurer, setInsurer] = useState('');
  const [policyNumber, setPolicyNumber] = useState('');
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
      const claim = await api.createDamageClaim({
        incidentDate: incidentDate || undefined,
        incidentLocation: incidentLocation || undefined,
        incidentDescription,
        insurer: insurer || undefined,
        policyNumber: policyNumber || undefined,
        vehicleId: vehicleId || undefined,
        documentIds: attachments.map((a) => a.id),
      });
      router.replace(`/daune/${claim.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <Link href="/daune" className="muted">
        ← Dosar de daună
      </Link>
      <h1>Dosar de daună nou</h1>
      {error ? (
        <div className="alert alert-err" role="alert">
          {error}
        </div>
      ) : null}
      <form onSubmit={submit} className="card stack" style={{ gap: 10 }}>
        <div className="field">
          <label htmlFor="incidentDate">Data evenimentului</label>
          <input id="incidentDate" type="date" value={incidentDate} onChange={(e) => setIncidentDate(e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="incidentLocation">Locul evenimentului</label>
          <input id="incidentLocation" type="text" maxLength={500} value={incidentLocation} onChange={(e) => setIncidentLocation(e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="incidentDescription">Descrierea evenimentului</label>
          <textarea id="incidentDescription" rows={3} value={incidentDescription} onChange={(e) => setIncidentDescription(e.target.value)} required style={inputStyle} />
        </div>
        <div className="field">
          <label htmlFor="insurer">Asigurător</label>
          <input id="insurer" type="text" maxLength={200} value={insurer} onChange={(e) => setInsurer(e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="policyNumber">Număr poliță</label>
          <input id="policyNumber" type="text" maxLength={100} value={policyNumber} onChange={(e) => setPolicyNumber(e.target.value)} />
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
          <label>Fotografii și documente</label>
          <AttachmentPicker files={attachments} onChange={setAttachments} />
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se trimite…' : 'Deschide dosarul'}
        </button>
      </form>
    </>
  );
}
