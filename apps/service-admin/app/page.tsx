'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type AdminVehicle } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';

/** Normalizare pentru căutare: litere mici, fără spații („MS 77 IST" = „ms77ist"). */
function norm(s: string): string {
  return s.toLowerCase().replace(/\s+/g, '');
}

export default function DashboardPage() {
  const router = useRouter();
  const [vehicles, setVehicles] = useState<AdminVehicle[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [qName, setQName] = useState('');
  const [qPlate, setQPlate] = useState('');
  const [qVin, setQVin] = useState('');

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
        <h1>Vehicule</h1>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <Link href="/import" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            📥 Import clienți (Excel)
          </Link>
          <Link href="/oferte" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            🧰 Cereri ofertă
          </Link>
          <Link href="/mesaje" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            💬 Mesaje
          </Link>
          <Link href="/asistenta" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            🆘 Asistență
          </Link>
          <Link href="/mobilitate" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            🚕 Mobilitate
          </Link>
          <Link href="/daune" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            📋 Daune
          </Link>
          <Link href="/taxe" className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }}>
            🧾 Taxe
          </Link>
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 12px' }} onClick={logout}>
            Ieșire
          </button>
        </div>
      </div>

      {error ? <ErrorState message={error} onRetry={load} /> : null}
      {!error && vehicles === null ? <Loading /> : null}
      {!error && vehicles?.length === 0 ? <EmptyState title="Niciun vehicul înregistrat" /> : null}

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
                      <label htmlFor="qName">Nume proprietar</label>
                      <input
                        id="qName"
                        type="search"
                        value={qName}
                        onChange={(e) => setQName(e.target.value)}
                        placeholder="ex. Popescu"
                      />
                    </div>
                    <div className="field" style={{ flex: '1 1 160px', margin: 0 }}>
                      <label htmlFor="qPlate">Nr. înmatriculare</label>
                      <input
                        id="qPlate"
                        type="search"
                        value={qPlate}
                        onChange={(e) => setQPlate(e.target.value)}
                        placeholder="ex. MS 01 POP"
                      />
                    </div>
                    <div className="field" style={{ flex: '1 1 180px', margin: 0 }}>
                      <label htmlFor="qVin">VIN</label>
                      <input
                        id="qVin"
                        type="search"
                        value={qVin}
                        onChange={(e) => setQVin(e.target.value)}
                        placeholder="ex. WBA3A5…"
                      />
                    </div>
                  </div>
                  {searching ? (
                    <div className="muted" style={{ fontSize: '0.82rem', marginTop: 6 }}>
                      {filtered.length} din {vehicles.length} vehicule ·{' '}
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
                        Șterge filtrele
                      </button>
                    </div>
                  ) : null}
                </div>
                {filtered.length === 0 ? (
                  <EmptyState
                    title="Niciun vehicul găsit"
                    hint="Niciun rezultat pentru filtrele introduse — căutați după numele proprietarului, numărul de înmatriculare sau VIN."
                  />
                ) : (
                  <div className="card">
                    {filtered.map((v) => (
                      <div key={v.id} className="list-row">
                        <div>
                          <strong>{v.plateNumber}</strong>
                          <div className="muted" style={{ fontSize: '0.85rem' }}>
                            {[v.make, v.model, v.year].filter(Boolean).join(' ')}
                            {v.ownerName ? ` · ${v.ownerName}` : ''}
                          </div>
                          <div className="muted" style={{ fontSize: '0.78rem' }}>VIN: {v.vin}</div>
                        </div>
                        <Link href={`/vehicule/${v.id}`}>Scadențe →</Link>
                      </div>
                    ))}
                  </div>
                )}
              </>
            );
          })()
        : null}
    </>
  );
}
