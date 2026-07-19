'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Deadline, type DeadlineType } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { DeadlineBadge, daysLeftText } from '@/components/DeadlineBadge';
import { DocumentControl } from '@/components/DocumentControl';
import { useT } from '@/lib/i18n';

const TYPES: { type: DeadlineType; label: string }[] = [
  { type: 'ITP', label: 'ITP' },
  { type: 'RCA', label: 'RCA' },
  { type: 'ROAD_TAX', label: 'Taxă de drum' },
  { type: 'ROADSIDE_ASSISTANCE', label: 'Asistență rutieră' },
];

export default function AdminVehicleDeadlinesPage() {
  const router = useRouter();
  const t = useT();
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

  if (error) return <ErrorState message={t(error)} onRetry={load} />;
  if (deadlines === null) return <Loading rows={3} />;

  return (
    <>
      <Link href="/" className="muted">
        {t('← Vehicule')}
      </Link>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>{t('Scadențe')}</h1>
        <Link href={`/vehicule/${params.id}/istoric`} className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
          {t('🧾 Istoric service')}
        </Link>
      </div>

      <div className="card">
        {deadlines.length === 0 ? <p className="muted">{t('Nicio scadență introdusă.')}</p> : null}
        {deadlines.map((d) => (
          <div key={d.id} className="stack" style={{ gap: 8 }}>
            <div className="list-row">
              <div>
                <strong>{t(d.typeLabel)}</strong>
                <div className="muted" style={{ fontSize: '0.82rem' }}>
                  {d.expiresAt ?? '—'} · {daysLeftText(t, d.daysLeft)} · {d.verified ? t('validat') : t('nevalidat')}
                </div>
                {d.type === 'ITP' ? (
                  <a
                    href="https://prog.rarom.ro/rarpol/"
                    target="_blank"
                    rel="noopener noreferrer"
                    style={{ fontSize: '0.82rem' }}
                  >
                    {t('Verificare ITP (RAR) ↗')}
                  </a>
                ) : null}
                {d.type === 'RCA' ? (
                  <a
                    href="https://www.aida.info.ro/polite-rca"
                    target="_blank"
                    rel="noopener noreferrer"
                    style={{ fontSize: '0.82rem' }}
                  >
                    {t('Verificare RCA (AIDA) ↗')}
                  </a>
                ) : null}
                {d.type === 'ROAD_TAX' ? (
                  <a
                    href="https://www.erovinieta.ro"
                    target="_blank"
                    rel="noopener noreferrer"
                    style={{ fontSize: '0.82rem' }}
                  >
                    {t('Verificare taxă de drum (eRovinieta) ↗')}
                  </a>
                ) : null}
              </div>
              <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
                <DeadlineBadge state={d.state} label={t(d.stateLabel)} />
                {!d.verified ? (
                  <button
                    className="btn"
                    style={{ width: 'auto', padding: '6px 12px' }}
                    disabled={busy === d.id}
                    onClick={() => validate(d.id)}
                  >
                    {busy === d.id ? '…' : t('Validează')}
                  </button>
                ) : null}
              </div>
            </div>
            <DocumentControl deadline={d} onChange={load} />
          </div>
        ))}
      </div>

      <h2>{t('Adaugă scadență (validată de service)')}</h2>
      <form onSubmit={add} className="card">
        <div className="field">
          <label htmlFor="type">{t('Tip')}</label>
          <select
            id="type"
            value={form.type}
            onChange={(e) => setForm((f) => ({ ...f, type: e.target.value }))}
            style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
          >
            {TYPES.map((ty) => (
              <option key={ty.type} value={ty.type}>
                {t(ty.label)}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label htmlFor="expiresAt">{t('Data expirării')}</label>
          <input id="expiresAt" type="date" value={form.expiresAt} onChange={(e) => setForm((f) => ({ ...f, expiresAt: e.target.value }))} required />
        </div>
        <button className="btn" type="submit" disabled={busy === 'add'}>
          {busy === 'add' ? t('Se salvează…') : t('Adaugă')}
        </button>
      </form>
    </>
  );
}
