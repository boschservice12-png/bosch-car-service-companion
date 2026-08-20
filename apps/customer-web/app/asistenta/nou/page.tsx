'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';

export default function NewRoadsidePage() {
  const router = useRouter();
  const t = useT();
  const [location, setLocation] = useState('');
  const [problem, setProblem] = useState('');
  const [mobility, setMobility] = useState('NOT_DRIVABLE');
  const [safety, setSafety] = useState('SAFE');
  const [phone, setPhone] = useState('');
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
      const req = await api.createRoadsideRequest({
        location,
        problem,
        mobility,
        safety,
        phone,
        vehicleId: vehicleId || undefined,
      });
      router.replace(`/asistenta/${req.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <header className="page-head">
        <div>
  <Link href="/asistenta" className="back-link">
          <Icon name="arrow-left" size={14} />
          {t('Asistență rutieră')}
        </Link>
          <h1>{t('Cerere de asistență')}</h1>
        </div>
      </header>
      {error ? (
        <div className="alert alert-err" role="alert">
          {t(error)}
        </div>
      ) : null}
      <form onSubmit={submit} className="panel panel-body panel-form stack">
        <div className="field">
          <label htmlFor="location">{t('Locația')}</label>
          <input id="location" type="text" maxLength={500} value={location} onChange={(e) => setLocation(e.target.value)} required placeholder={t('Ex: DN13, km 12')} />
        </div>
        <div className="field">
          <label htmlFor="problem">{t('Problema')}</label>
          <textarea id="problem" rows={3} value={problem} onChange={(e) => setProblem(e.target.value)} required />
        </div>
        <div className="field">
          <label htmlFor="mobility">{t('Mașina se poate deplasa?')}</label>
          <select id="mobility" value={mobility} onChange={(e) => setMobility(e.target.value)}>
            <option value="DRIVABLE">{t('Da, se poate deplasa')}</option>
            <option value="NOT_DRIVABLE">{t('Nu, este imobilizată')}</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="safety">{t('Sunteți în siguranță?')}</label>
          <select id="safety" value={safety} onChange={(e) => setSafety(e.target.value)}>
            <option value="SAFE">{t('Da, în siguranță')}</option>
            <option value="AT_RISK">{t('Nu, situație periculoasă')}</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="phone">{t('Telefon de contact')}</label>
          <input id="phone" type="tel" maxLength={40} value={phone} onChange={(e) => setPhone(e.target.value)} required placeholder="+40…" />
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
          {busy ? t('Se trimite…') : t('Trimite cererea')}
        </button>
      </form>
    </>
  );
}
