'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';

export default function NewTaxPage() {
  const router = useRouter();
  const t = useT();
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
      <header className="page-head">
        <div>
  <Link href="/taxe" className="back-link">
          <Icon name="arrow-left" size={14} />
          {t('Taxe și impozite')}
        </Link>
          <h1>{t('Taxă nouă')}</h1>
        </div>
      </header>
      {error ? (
        <div className="alert alert-err" role="alert">
          {t(error)}
        </div>
      ) : null}
      <form onSubmit={submit} className="panel panel-body panel-form stack">
        <div className="field">
          <label htmlFor="year">{t('An')}</label>
          <input id="year" type="number" min={2000} max={2100} value={year} onChange={(e) => setYear(e.target.value)} required />
        </div>
        <div className="field">
          <label htmlFor="type">{t('Tip')}</label>
          <select id="type" value={type} onChange={(e) => setType(e.target.value)}>
            <option value="VEHICLE_TAX">{t('Impozit auto')}</option>
            <option value="ENVIRONMENT">{t('Taxă de mediu')}</option>
            <option value="OTHER">{t('Altă taxă')}</option>
          </select>
        </div>
        <div className="field">
          <label htmlFor="amount">{t('Sumă (RON)')}</label>
          <input id="amount" type="number" min={0} step="0.01" inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} required />
        </div>
        <div className="field">
          <label htmlFor="dueDate">{t('Scadență (opțional)')}</label>
          <input id="dueDate" type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
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
          {busy ? t('Se salvează…') : t('Adaugă taxa')}
        </button>
      </form>
    </>
  );
}
