'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Deadline, type DeadlineType } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { DeadlineBadge, daysLeftText } from '@/components/DeadlineBadge';

const TYPES: { type: DeadlineType; label: string }[] = [
  { type: 'ITP', label: 'ITP' },
  { type: 'RCA', label: 'RCA' },
  { type: 'ROAD_TAX', label: 'Rovinietă' },
  { type: 'ROADSIDE_ASSISTANCE', label: 'Asistență rutieră' },
];

export default function AdminVehicleDeadlinesPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [deadlines, setDeadlines] = useState<Deadline[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [form, setForm] = useState({ type: 'ITP', expiresAt: '' });

  const load = useCallback(() => {
    setError(null);
    api
      .vehicleDeadlines(params.id)
      .then(setDeadlines)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  async function validate(id: string) {
    setBusy(id);
    try {
      await api.verifyDeadline(id);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Validare eșuată.');
    } finally {
      setBusy(null);
    }
  }

  async function add(e: React.FormEvent) {
    e.preventDefault();
    setBusy('add');
    try {
      await api.createDeadline(params.id, { type: form.type, expiresAt: form.expiresAt });
      setForm({ type: 'ITP', expiresAt: '' });
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Adăugare eșuată.');
    } finally {
      setBusy(null);
    }
  }

  if (error) return <ErrorState message={error} onRetry={load} />;
  if (deadlines === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/" className="muted">
        ← Vehicule
      </Link>
      <h1>Scadențe</h1>

      <div className="card">
        {deadlines.length === 0 ? <p className="muted">Nicio scadență introdusă.</p> : null}
        {deadlines.map((d) => (
          <div key={d.id} className="list-row">
            <div>
              <strong>{d.typeLabel}</strong>
              <div className="muted" style={{ fontSize: '0.82rem' }}>
                {d.expiresAt ?? '—'} · {daysLeftText(d.daysLeft)} · {d.verified ? 'validat' : 'nevalidat'}
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
              <DeadlineBadge state={d.state} label={d.stateLabel} />
              {!d.verified ? (
                <button
                  className="btn"
                  style={{ width: 'auto', padding: '6px 12px' }}
                  disabled={busy === d.id}
                  onClick={() => validate(d.id)}
                >
                  {busy === d.id ? '…' : 'Validează'}
                </button>
              ) : null}
            </div>
          </div>
        ))}
      </div>

      <h2>Adaugă scadență (validată de service)</h2>
      <form onSubmit={add} className="card">
        <div className="field">
          <label htmlFor="type">Tip</label>
          <select
            id="type"
            value={form.type}
            onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
            style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
          >
            {TYPES.map((t) => (
              <option key={t.type} value={t.type}>
                {t.label}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label htmlFor="expiresAt">Data expirării</label>
          <input id="expiresAt" type="date" value={form.expiresAt} onChange={(e) => setForm((f) => ({ ...f, expiresAt: e.target.value }))} required />
        </div>
        <button className="btn" type="submit" disabled={busy === 'add'}>
          {busy === 'add' ? 'Se salvează…' : 'Adaugă'}
        </button>
      </form>
    </>
  );
}
