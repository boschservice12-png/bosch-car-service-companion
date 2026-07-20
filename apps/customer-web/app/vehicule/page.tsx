'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

export default function VehiclesPage() {
  const router = useRouter();
  const t = useT();
  const [vehicles, setVehicles] = useState<Vehicle[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  // Blocul 3: activarea unui vehicul cu un cod primit de la service.
  const [code, setCode] = useState('');
  const [actMsg, setActMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [actBusy, setActBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    setVehicles(null);
    api
      .vehicles()
      .then(setVehicles)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Nu am putut încărca vehiculele.');
      });
  }, [router]);

  useEffect(load, [load]);

  async function activate(e: React.FormEvent) {
    e.preventDefault();
    setActMsg(null);
    setActBusy(true);
    try {
      const v = await api.activateVehicle(code.trim());
      setActMsg({ ok: true, text: t('Vehicul activat: {plate}', { plate: v.plateNumber }) });
      setCode('');
      load();
    } catch (err) {
      if (err instanceof ApiError && err.httpStatus === 401) {
        router.replace('/login');
        return;
      }
      setActMsg({ ok: false, text: err instanceof ApiError ? err.problem.title : t('Cod de activare invalid sau expirat.') });
    } finally {
      setActBusy(false);
    }
  }

  return (
    <>
      <h1>{t('Vehiculele mele')}</h1>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}

      {!error && vehicles === null ? <Loading /> : null}

      {!error && vehicles !== null && vehicles.length === 0 ? (
        <EmptyState
          title={t('Niciun vehicul încă')}
          hint={t('Adăugați primul vehicul pentru a urmări scadențele și istoricul.')}
          action={
            <Link className="btn" href="/vehicule/nou">
              {t('➕ Adaugă vehicul')}
            </Link>
          }
        />
      ) : null}

      {!error && vehicles && vehicles.length > 0 ? (
        <>
          <div className="card">
            {vehicles.map((v) => (
              <div key={v.id} className="list-row">
                <div>
                  <strong>{v.plateNumber}</strong>
                  <div className="muted" style={{ fontSize: '0.85rem' }}>
                    {[v.make, v.model, v.year].filter(Boolean).join(' · ') || v.vin}
                  </div>
                </div>
                <Link href={`/vehicule/${v.id}`} aria-label={t('Detalii {plate}', { plate: v.plateNumber })}>
                  {t('Detalii →')}
                </Link>
              </div>
            ))}
          </div>
          <Link className="btn btn-ghost" href="/vehicule/nou">
            {t('➕ Adaugă alt vehicul')}
          </Link>
        </>
      ) : null}

      <section className="card stack" style={{ marginTop: 16 }}>
        <h2 style={{ margin: 0, fontSize: '1rem' }}>{t('Ai un cod de la service?')}</h2>
        <p className="muted" style={{ fontSize: '0.85rem', margin: 0 }}>
          {t('Introduceți codul de activare primit de la service pentru a adăuga vehiculul în contul dumneavoastră.')}
        </p>
        {actMsg ? (
          <div className={`alert ${actMsg.ok ? 'alert-ok' : 'alert-err'}`} role="alert">
            {actMsg.text}
          </div>
        ) : null}
        <form onSubmit={activate} className="stack" style={{ gap: 8 }}>
          <input
            aria-label={t('Cod de activare')}
            value={code}
            onChange={(e) => setCode(e.target.value)}
            placeholder="XXXX-XXXX-XXXX-XXXX"
            autoCapitalize="characters"
          />
          <button className="btn" type="submit" disabled={actBusy || code.trim() === ''}>
            {actBusy ? t('Se activează…') : t('Activează vehiculul')}
          </button>
        </form>
      </section>

      <BottomNav />
    </>
  );
}
