'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: 12,
  border: '1px solid var(--border)',
  borderRadius: 8,
  fontSize: '1rem',
  background: '#fff',
};

export default function NewQuoteRequestPage() {
  const router = useRouter();
  const t = useT();
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [vehicleId, setVehicleId] = useState('');
  const [mileage, setMileage] = useState('');
  const [symptomDescription, setSymptomDescription] = useState('');
  const [occurrenceConditions, setOccurrenceConditions] = useState('');
  const [vehicleDrivable, setVehicleDrivable] = useState(true);
  const [warningLights, setWarningLights] = useState('');
  const [preferredContactMethod, setPreferredContactMethod] = useState('PHONE');
  const [preferredInterval, setPreferredInterval] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api
      .vehicles()
      .then((list) => {
        setVehicles(list);
        if (list.length === 1 && list[0]) setVehicleId(list[0].id);
      })
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) router.replace('/login');
      });
  }, [router]);

  async function submit(asDraft: boolean) {
    setError(null);
    setBusy(true);
    try {
      const created = await api.createQuoteRequest({
        vehicleId: vehicleId || null,
        mileage: mileage !== '' ? Number.parseInt(mileage, 10) : null,
        symptomDescription,
        occurrenceConditions: occurrenceConditions || null,
        vehicleDrivable,
        warningLights: warningLights || null,
        preferredContactMethod: preferredContactMethod || null,
        preferredInterval: preferredInterval || null,
        draft: asDraft,
      });
      router.replace(`/oferte/${created.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <Link href="/oferte" className="muted">
        {t('← Cereri de ofertă')}
      </Link>
      <h1>{t('Cere ofertă de preț')}</h1>
      <p className="muted">{t('Descrieți problema — service-ul revine cu o estimare. Fără obligații.')}</p>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <form
        onSubmit={(e) => {
          e.preventDefault();
          void submit(false);
        }}
        className="card stack"
        style={{ gap: 12 }}
      >
        <div className="field">
          <label htmlFor="vehicle">{t('Vehicul (opțional)')}</label>
          <select id="vehicle" value={vehicleId} onChange={(e) => setVehicleId(e.target.value)} style={inputStyle}>
            <option value="">{t('— fără vehicul —')}</option>
            {vehicles.map((v) => (
              <option key={v.id} value={v.id}>
                {v.plateNumber} · {v.vin}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label htmlFor="mileage">{t('Kilometraj (opțional)')}</label>
          <input id="mileage" type="number" min={0} inputMode="numeric" value={mileage} onChange={(e) => setMileage(e.target.value)} style={inputStyle} />
        </div>
        <div className="field">
          <label htmlFor="symptom">{t('Descrierea simptomului *')}</label>
          <textarea
            id="symptom"
            value={symptomDescription}
            onChange={(e) => setSymptomDescription(e.target.value)}
            rows={3}
            minLength={10}
            required
            placeholder={t('Ex.: zgomot metalic la frânare, în față…')}
            style={inputStyle}
          />
        </div>
        <div className="field">
          <label htmlFor="conditions">{t('Când apare (opțional)')}</label>
          <input id="conditions" type="text" value={occurrenceConditions} onChange={(e) => setOccurrenceConditions(e.target.value)} placeholder={t('Ex.: la frânări puternice')} style={inputStyle} />
        </div>
        <div className="field">
          <label htmlFor="drivable">{t('Mașina e conducibilă?')}</label>
          <select id="drivable" value={vehicleDrivable ? 'DA' : 'NU'} onChange={(e) => setVehicleDrivable(e.target.value === 'DA')} style={inputStyle}>
            <option value="DA">{t('Da')}</option>
            <option value="NU">{t('Nu')}</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="lights">{t('Martori aprinși în bord (opțional)')}</label>
          <input id="lights" type="text" value={warningLights} onChange={(e) => setWarningLights(e.target.value)} placeholder={t('Ex.: ABS, motor')} style={inputStyle} />
        </div>
        <div className="field">
          <label htmlFor="contact">{t('Contact preferat')}</label>
          <select id="contact" value={preferredContactMethod} onChange={(e) => setPreferredContactMethod(e.target.value)} style={inputStyle}>
            <option value="PHONE">{t('Telefon')}</option>
            <option value="WHATSAPP">WhatsApp</option>
            <option value="APP">{t('Mesaj în aplicație')}</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="interval">{t('Interval preferat (opțional)')}</label>
          <input id="interval" type="text" value={preferredInterval} onChange={(e) => setPreferredInterval(e.target.value)} placeholder={t('Ex.: luni–vineri după 16:00')} style={inputStyle} />
        </div>

        <button className="btn" type="submit" disabled={busy}>
          {busy ? t('Se trimite…') : t('Trimite cererea')}
        </button>
        <button className="btn btn-ghost" type="button" disabled={busy} onClick={() => void submit(true)}>
          {t('Salvează ca ciornă')}
        </button>
      </form>

      <BottomNav />
    </>
  );
}
