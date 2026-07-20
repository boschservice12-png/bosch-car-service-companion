'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type AdminVehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { Loading, EmptyState, ErrorState } from '@/components/states';

/** Normalizare pentru căutare: litere mici, fără spații („MS 77 IST" = „ms77ist"). */
function norm(s: string): string {
  return s.toLowerCase().replace(/\s+/g, '');
}

/** P2-03: lista se randează incremental — flote mari nu blochează pagina. */
const PAGE_SIZE = 50;

export default function DashboardPage() {
  const router = useRouter();
  const t = useT();
  const [vehicles, setVehicles] = useState<AdminVehicle[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [qName, setQName] = useState('');
  const [qPlate, setQPlate] = useState('');
  const [qVin, setQVin] = useState('');
  const [visibleCount, setVisibleCount] = useState(PAGE_SIZE);
  // Blocul 3: codul de activare emis pentru un vehicul (afișat o singură dată).
  const [issued, setIssued] = useState<{ plate: string; token: string } | null>(null);

  async function issueCode(vehicleId: string, plate: string) {
    setIssued(null);
    try {
      const res = await api.issueActivationToken(vehicleId);
      setIssued({ plate, token: res.token });
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : t('Nu am putut emite codul.'));
    }
  }

  // La schimbarea filtrelor, fereastra de afișare revine la început.
  useEffect(() => {
    setVisibleCount(PAGE_SIZE);
  }, [qName, qPlate, qVin]);

  const load = useCallback(() => {
    setError(null);
    setVehicles(null);
    api
      .adminVehicles()
      .then(setVehicles)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Nu am putut încărca vehiculele.');
      });
  }, [router]);

  useEffect(load, [load]);

  async function logout() {
    await api.logout().catch(() => undefined);
    router.replace('/login');
  }

  return (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>{t('Vehicule')}</h1>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <Link href="/import" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('📥 Import clienți (Excel)')}
          </Link>
          <Link href="/oferte" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('🧰 Cereri ofertă')}
          </Link>
          <Link href="/mesaje" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('💬 Mesaje')}
          </Link>
          <Link href="/asistenta" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('🆘 Asistență')}
          </Link>
          <Link href="/mobilitate" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('🚕 Mobilitate')}
          </Link>
          <Link href="/daune" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('📋 Daune')}
          </Link>
          <Link href="/taxe" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('🧾 Taxe')}
          </Link>
          <Link href="/notificari" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('🔔 Notificări')}
          </Link>
          <Link href="/securitate" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            {t('🔐 Securitate')}
          </Link>
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} onClick={logout}>
            {t('Ieșire')}
          </button>
        </div>
      </div>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && vehicles === null ? <Loading /> : null}
      {!error && vehicles?.length === 0 ? <EmptyState title={t('Niciun vehicul înregistrat')} /> : null}

      {vehicles && vehicles.length > 0
        ? (() => {
            const nName = norm(qName);
            const nPlate = norm(qPlate);
            const nVin = norm(qVin);
            const searching = nName !== '' || nPlate !== '' || nVin !== '';
            const filtered = searching
              ? vehicles.filter(
                  (v) =>
                    (nName === '' || norm(v.ownerName ?? '').includes(nName)) &&
                    (nPlate === '' || norm(v.plateNumber).includes(nPlate)) &&
                    (nVin === '' || norm(v.vin).includes(nVin)),
                )
              : vehicles;
            return (
              <>
                <div className="card" style={{ marginTop: 12 }}>
                  <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                    <div className="field" style={{ flex: '1 1 180px', margin: 0 }}>
                      <label htmlFor="qName">{t('Nume proprietar')}</label>
                      <input
                        id="qName"
                        type="search"
                        value={qName}
                        onChange={(e) => setQName(e.target.value)}
                        placeholder={t('ex. Popescu')}
                      />
                    </div>
                    <div className="field" style={{ flex: '1 1 160px', margin: 0 }}>
                      <label htmlFor="qPlate">{t('Nr. înmatriculare')}</label>
                      <input
                        id="qPlate"
                        type="search"
                        value={qPlate}
                        onChange={(e) => setQPlate(e.target.value)}
                        placeholder={t('ex. MS 01 POP')}
                      />
                    </div>
                    <div className="field" style={{ flex: '1 1 180px', margin: 0 }}>
                      <label htmlFor="qVin">VIN</label>
                      <input
                        id="qVin"
                        type="search"
                        value={qVin}
                        onChange={(e) => setQVin(e.target.value)}
                        placeholder={t('ex. WBA3A5…')}
                      />
                    </div>
                  </div>
                  {searching ? (
                    <div className="muted" style={{ fontSize: '0.82rem', marginTop: 6 }}>
                      {t('{n} din {m} vehicule', { n: filtered.length, m: vehicles.length })} ·{' '}
                      <button
                        type="button"
                        className="btn btn-ghost"
                        style={{ width: 'auto', padding: '2px 8px', fontSize: '0.82rem' }}
                        onClick={() => {
                          setQName('');
                          setQPlate('');
                          setQVin('');
                        }}
                      >
                        {t('Șterge filtrele')}
                      </button>
                    </div>
                  ) : null}
                </div>
                {filtered.length === 0 ? (
                  <EmptyState
                    title={t('Niciun vehicul găsit')}
                    hint={t('Niciun rezultat pentru filtrele introduse — căutați după numele proprietarului, numărul de înmatriculare sau VIN.')}
                  />
                ) : (
                  <div className="card">
                    {issued ? (
                      <div className="alert alert-ok" role="status" style={{ marginBottom: 8 }}>
                        <div>
                          <strong>{t('Cod de activare pentru {plate}', { plate: issued.plate })}:</strong>{' '}
                          <code className="tabnum">{issued.token}</code>
                        </div>
                        <div className="muted" style={{ fontSize: '0.8rem' }}>
                          {t('Comunicați acest cod clientului. Se afișează O SINGURĂ DATĂ și expiră în 7 zile.')}
                        </div>
                      </div>
                    ) : null}
                    {filtered.slice(0, visibleCount).map((v) => (
                      <div key={v.id} className="list-row">
                        <div>
                          <strong>{v.plateNumber}</strong>
                          <div className="muted" style={{ fontSize: '0.85rem' }}>
                            {[v.make, v.model, v.year].filter(Boolean).join(' ')}
                            {v.ownerName ? ` · ${v.ownerName}` : ''}
                          </div>
                          <div className="muted" style={{ fontSize: '0.78rem' }}>VIN: {v.vin}</div>
                        </div>
                        <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                          <button
                            type="button"
                            className="btn btn-ghost"
                            style={{ width: 'auto', padding: '6px 10px' }}
                            onClick={() => issueCode(v.id, v.plateNumber)}
                          >
                            {t('🔑 Cod activare')}
                          </button>
                          <Link href={`/vehicule/${v.id}`}>{t('Scadențe →')}</Link>
                        </div>
                      </div>
                    ))}
                    {filtered.length > visibleCount ? (
                      <button
                        type="button"
                        className="btn btn-ghost"
                        style={{ width: '100%', marginTop: 8 }}
                        onClick={() => setVisibleCount((n) => n + PAGE_SIZE)}
                      >
                        {t('Afișează încă {n} (din {m} rămase)', {
                          n: Math.min(PAGE_SIZE, filtered.length - visibleCount),
                          m: filtered.length - visibleCount,
                        })}
                      </button>
                    ) : null}
                  </div>
                )}
              </>
            );
          })()
        : null}
    </>
  );
}
