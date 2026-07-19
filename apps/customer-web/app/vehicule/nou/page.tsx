'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError } from '@/lib/types';
import { useT } from '@/lib/i18n';

export default function NewVehiclePage() {
  const router = useRouter();
  const t = useT();
  const [form, setForm] = useState({ vin: '', plateNumber: '', make: '', model: '', year: '' });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [general, setGeneral] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function set<K extends keyof typeof form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    setGeneral(null);
    setBusy(true);
    try {
      await api.createVehicle({
        vin: form.vin.trim().toUpperCase(),
        plateNumber: form.plateNumber.trim().toUpperCase(),
        make: form.make || undefined,
        model: form.model || undefined,
        year: form.year ? Number(form.year) : undefined,
      });
      router.replace('/vehicule');
    } catch (err) {
      if (err instanceof ApiError && err.problem.errors) {
        setErrors(err.problem.errors);
      } else {
        setGeneral(err instanceof ApiError ? err.problem.title : t('A apărut o eroare.'));
      }
    } finally {
      setBusy(false);
    }
  }

  const fieldErr = (name: string) => errors[name]?.[0];

  return (
    <>
      <h1>{t('Adaugă vehicul')}</h1>
      {general ? (
        <div className="alert alert-err" role="alert">
          {general}
        </div>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <div className="field">
          <label htmlFor="vin">{t('Serie de șasiu (VIN)')}</label>
          <input id="vin" value={form.vin} onChange={(e) => set('vin', e.target.value)} maxLength={17} required />
          <div className="hint">{t('17 caractere, fără literele I, O, Q.')}</div>
          {fieldErr('vin') ? <div className="err">{fieldErr('vin')}</div> : null}
        </div>
        <div className="field">
          <label htmlFor="plate">{t('Număr de înmatriculare')}</label>
          <input id="plate" value={form.plateNumber} onChange={(e) => set('plateNumber', e.target.value)} required />
          {fieldErr('plateNumber') ? <div className="err">{fieldErr('plateNumber')}</div> : null}
        </div>
        <div className="field">
          <label htmlFor="make">{t('Marcă')}</label>
          <input id="make" value={form.make} onChange={(e) => set('make', e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="model">{t('Model')}</label>
          <input id="model" value={form.model} onChange={(e) => set('model', e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="year">{t('An fabricație')}</label>
          <input id="year" type="number" inputMode="numeric" value={form.year} onChange={(e) => set('year', e.target.value)} />
          {fieldErr('year') ? <div className="err">{fieldErr('year')}</div> : null}
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? t('Se salvează…') : t('Salvează vehiculul')}
        </button>
        <button type="button" className="btn btn-ghost" style={{ marginTop: 10 }} onClick={() => router.back()}>
          {t('Renunță')}
        </button>
      </form>
    </>
  );
}
