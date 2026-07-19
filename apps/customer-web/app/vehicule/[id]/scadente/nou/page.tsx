'use client';

import { useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type DeadlineType } from '@/lib/types';

const TYPES: { value: DeadlineType; label: string }[] = [
  { value: 'ITP', label: 'ITP' },
  { value: 'RCA', label: 'RCA' },
  { value: 'ROAD_TAX', label: 'Taxă de drum' },
  { value: 'ROADSIDE_ASSISTANCE', label: 'Asistență rutieră' },
];

export default function NewDeadlinePage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [form, setForm] = useState({ type: 'ITP', expiresAt: '', validFrom: '', note: '' });
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
      await api.createDeadline(params.id, {
        type: form.type,
        expiresAt: form.expiresAt,
        validFrom: form.validFrom || undefined,
        note: form.note || undefined,
      });
      router.replace(`/vehicule/${params.id}`);
    } catch (err) {
      if (err instanceof ApiError && err.problem.errors) setErrors(err.problem.errors);
      else setGeneral(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
    } finally {
      setBusy(false);
    }
  }

  const fieldErr = (name: string) => errors[name]?.[0];

  return (
    <>
      <h1>Adaugă scadență</h1>
      {general ? (
        <div className="alert alert-err" role="alert">
          {general}
        </div>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <div className="field">
          <label htmlFor="type">Tip</label>
          <select
            id="type"
            value={form.type}
            onChange={(e) => set('type', e.target.value)}
            style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
          >
            {TYPES.map((t) => (
              <option key={t.value} value={t.value}>
                {t.label}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label htmlFor="expiresAt">Data expirării</label>
          <input id="expiresAt" type="date" value={form.expiresAt} onChange={(e) => set('expiresAt', e.target.value)} required />
          {fieldErr('expiresAt') ? <div className="err">{fieldErr('expiresAt')}</div> : null}
        </div>
        <div className="field">
          <label htmlFor="validFrom">Valabil de la (opțional)</label>
          <input id="validFrom" type="date" value={form.validFrom} onChange={(e) => set('validFrom', e.target.value)} />
          {fieldErr('validFrom') ? <div className="err">{fieldErr('validFrom')}</div> : null}
        </div>
        <div className="field">
          <label htmlFor="note">Notă (opțional)</label>
          <input id="note" value={form.note} onChange={(e) => set('note', e.target.value)} />
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se salvează…' : 'Salvează scadența'}
        </button>
        <button type="button" className="btn btn-ghost" style={{ marginTop: 10 }} onClick={() => router.back()}>
          Renunță
        </button>
      </form>
    </>
  );
}
