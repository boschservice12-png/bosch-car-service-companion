'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';

export default function NewMobilityPage() {
  const router = useRouter();
  const t = useT();
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
      <header className="page-head">
        <div>
  <Link href="/mobilitate" className="back-link">
          <Icon name="arrow-left" size={14} />
          {t('Mobilitate')}
        </Link>
          <h1>{t('Solicitare de mobilitate')}</h1>
        </div>
      </header>
      {error ? (
        <div className="alert alert-err" role="alert">
          {t(error)}
        </div>
      ) : null}
      <form onSubmit={submit} className="panel panel-body panel-form stack">
        <div className="field">
          <label htmlFor="type">{t('Tip')}</label>
          <select id="type" value={type} onChange={(e) => setType(e.target.value)}>
            <option value="REPLACEMENT_CAR">{t('Mașină de înlocuire')}</option>
            <option value="TAXI">{t('Taxi')}</option>
            <option value="PERSON_TRANSPORT">{t('Transport persoane')}</option>
            <option value="ACCOMMODATION">{t('Cazare')}</option>
            <option value="OTHER">{t('Altă solicitare')}</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="details">{t('Detalii')}<Icon name="chevron" size={14} /></label>
          <textarea id="details" rows={3} value={details} onChange={(e) => setDetails(e.target.value)} required />
        </div>
        <div className="field">
          <label htmlFor="preferredDate">{t('Data preferată (opțional)')}</label>
          <input id="preferredDate" type="date" value={preferredDate} onChange={(e) => setPreferredDate(e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="vehicle">{t('Vehicul (opțional)')}</label>
          <select id="vehicle" value={vehicleId} onChange={(e) => setVehicleId(e.target.value)}>
            <option value="">{t('— fără vehicul —')}</option>
            {vehicles.map((v) => (
              <option key={v.id} value={v.id}>
                {v.plateNumber}
              </option>
            ))}
          </select>
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? t('Se trimite…') : t('Trimite solicitarea')}
        </button>
      </form>
    </>
  );
}
