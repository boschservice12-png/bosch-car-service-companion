'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Deadline, type DeadlineType, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading, ErrorState } from '@/components/states';
import { DeadlineBadge, daysLeftText } from '@/components/DeadlineBadge';
import { DocumentControl } from '@/components/DocumentControl';

const TYPES: { type: DeadlineType; label: string }[] = [
  { type: 'ITP', label: 'ITP' },
  { type: 'RCA', label: 'RCA' },
  { type: 'ROAD_TAX', label: 'Taxă de drum' },
  { type: 'ROADSIDE_ASSISTANCE', label: 'Asistență rutieră' },
];

export default function VehicleDetailPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [vehicle, setVehicle] = useState<Vehicle | null>(null);
  const [deadlines, setDeadlines] = useState<Deadline[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    Promise.all([api.vehicles(), api.vehicleDeadlines(params.id)])
      .then(([list, dls]) => {
        const found = list.find((v) => v.id === params.id) ?? null;
        if (!found) setError('Vehiculul nu a fost găsit sau nu vă aparține.');
        setVehicle(found);
        setDeadlines(dls);
      })
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        if (err instanceof ApiError && (err.httpStatus === 403 || err.httpStatus === 404)) {
          setError('Vehiculul nu a fost găsit sau nu vă aparține.');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  if (error) return <ErrorState message={t(error)} onRetry={load} />;
  if (!vehicle || deadlines === null) return <Loading rows={3} />;

  const byType = new Map(deadlines.map((d) => [d.type, d]));

  return (
    <>
      <h1>{vehicle.plateNumber}</h1>
      <div className="card stack">
        <div>
          <span className="muted">{t('VIN:')}</span> {vehicle.vin}
        </div>
        {vehicle.make ? (
          <div>
            <span className="muted">{t('Vehicul:')}</span>{' '}
            {[vehicle.make, vehicle.model, vehicle.year].filter(Boolean).join(' ')}
          </div>
        ) : null}
      </div>

      <h2>{t('Scadențe')}</h2>
      <div className="card">
        {TYPES.map(({ type, label }) => {
          const d = byType.get(type);
          return (
            <div key={type} className="stack" style={{ gap: 8 }}>
              <div className="list-row">
                <div>
                  <strong>{t(label)}</strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {d
                      ? `${d.expiresAt ?? '—'} · ${daysLeftText(t, d.daysLeft)}${d.verified ? t(' · validat') : ''}`
                      : t('neintrodus')}
                  </div>
                  {type === 'ITP' ? (
                    <a
                      href="https://prog.rarom.ro/rarpol/"
                      target="_blank"
                      rel="noopener noreferrer"
                      style={{ fontSize: '0.82rem' }}
                    >
                      {t('Verificare ITP (RAR) ↗')}
                    </a>
                  ) : null}
                  {type === 'RCA' ? (
                    <a
                      href="https://www.aida.info.ro/polite-rca"
                      target="_blank"
                      rel="noopener noreferrer"
                      style={{ fontSize: '0.82rem' }}
                    >
                      {t('Verificare RCA (AIDA) ↗')}
                    </a>
                  ) : null}
                  {type === 'ROAD_TAX' ? (
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
                <DeadlineBadge state={d?.state ?? 'UNKNOWN'} label={t(d?.stateLabel ?? 'Necunoscut')} />
              </div>
              {d ? <DocumentControl deadline={d} onChange={load} /> : null}
            </div>
          );
        })}
      </div>
      <Link className="btn" href={`/vehicule/${vehicle.id}/scadente/nou`}>
        {t('➕ Adaugă / actualizează scadență')}
      </Link>
      <Link className="btn btn-ghost" href={`/vehicule/${vehicle.id}/istoric`}>
        {t('🧾 Istoric service')}
      </Link>
      <p className="muted" style={{ fontSize: '0.82rem' }}>
        {t('Stările se calculează pe baza datelor introduse și validate; aplicația nu interoghează baze oficiale.')}
      </p>

      <BottomNav />
    </>
  );
}
