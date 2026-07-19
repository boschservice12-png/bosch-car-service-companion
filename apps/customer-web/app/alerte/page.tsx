'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Deadline, type Vehicle } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading, ErrorState, EmptyState } from '@/components/states';
import { DeadlineBadge, daysLeftText } from '@/components/DeadlineBadge';

/**
 * Alerte — toate scadențele (ITP, RCA, taxă de drum, asistență rutieră) ale
 * vehiculelor PROPRII, într-un singur loc. Datele vin strict din vehiculele
 * clientului autentificat (autorizare la nivel de obiect pe fiecare apel).
 */
interface AlertRow {
  vehicle: Vehicle;
  deadline: Deadline;
}

/** Alerte cu verificare externă: ITP la RAR, RCA la AIDA — se deschid direct. */
const EXTERNAL_CHECK: Partial<Record<Deadline['type'], { href: string; hint: string }>> = {
  ITP: { href: 'https://prog.rarom.ro/rarpol/', hint: ' · verificare ITP (RAR) ↗' },
  RCA: { href: 'https://www.aida.info.ro/polite-rca', hint: ' · verificare RCA (AIDA) ↗' },
  ROAD_TAX: { href: 'https://www.erovinieta.ro', hint: ' · verificare taxă de drum (eRovinieta) ↗' },
};

export default function AlertsPage() {
  const router = useRouter();
  const [rows, setRows] = useState<AlertRow[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .vehicles()
      .then(async (vehicles) => {
        const perVehicle = await Promise.all(
          vehicles.map(async (vehicle) => {
            const deadlines = await api.vehicleDeadlines(vehicle.id);
            return deadlines.map((deadline) => ({ vehicle, deadline }));
          }),
        );
        const flat = perVehicle.flat();
        flat.sort((a, b) => {
          const da = a.deadline.daysLeft ?? Number.MAX_SAFE_INTEGER;
          const db = b.deadline.daysLeft ?? Number.MAX_SAFE_INTEGER;
          return da - db;
        });
        setRows(flat);
      })
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [router]);

  useEffect(load, [load]);

  if (error) return <ErrorState message={error} onRetry={load} />;
  if (rows === null) return <Loading rows={4} />;

  return (
    <>
      <Link href="/" className="muted">
        ← Acasă
      </Link>
      <h1>Alerte</h1>
      <p className="muted" style={{ fontSize: '0.85rem' }}>
        ITP · RCA · Taxă de drum · Asistență rutieră — pentru toate vehiculele dumneavoastră.
      </p>

      {rows.length === 0 ? (
        <EmptyState
          title="Nicio alertă"
          hint="Adăugați scadențele (ITP, RCA, taxă de drum) pe pagina fiecărui vehicul."
        />
      ) : (
        <div className="stack">
          {rows.map(({ vehicle, deadline }) => {
            const external = EXTERNAL_CHECK[deadline.type];
            const body = (
              <div className="list-row">
                <div>
                  <strong>
                    {deadline.typeLabel} — {vehicle.plateNumber}
                  </strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {deadline.expiresAt ?? '—'} · {daysLeftText(deadline.daysLeft)}
                    {deadline.verified ? ' · validat de service' : ''}
                    {external?.hint ?? ''}
                  </div>
                </div>
                <DeadlineBadge state={deadline.state} label={deadline.stateLabel} />
              </div>
            );
            return external ? (
              <a
                key={deadline.id}
                href={external.href}
                target="_blank"
                rel="noopener noreferrer"
                className="card"
                style={{ textDecoration: 'none', color: 'inherit' }}
              >
                {body}
              </a>
            ) : (
              <Link
                key={deadline.id}
                href={`/vehicule/${vehicle.id}`}
                className="card"
                style={{ textDecoration: 'none', color: 'inherit' }}
              >
                {body}
              </Link>
            );
          })}
        </div>
      )}

      <Link className="btn btn-ghost" href="/vehicule">
        🚗 Gestionează scadențele pe vehicul
      </Link>
      <p className="muted" style={{ fontSize: '0.82rem' }}>
        Stările se calculează pe baza datelor introduse și validate; aplicația nu interoghează baze oficiale.
      </p>

      <BottomNav />
    </>
  );
}
