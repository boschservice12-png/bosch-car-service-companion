'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
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
      <header className="page-head">
        <div>
          <Link href="/vehicule" className="back-link">
            <Icon name="arrow-left" size={14} />
            {t('Vehiculele mele')}
          </Link>
          <h1 className="plate">{vehicle.plateNumber}</h1>
          <p className="muted">
            {[vehicle.make, vehicle.model, vehicle.year].filter(Boolean).join(' ') || vehicle.vin}
          </p>
        </div>
        <div className="page-head-actions">
          <Link className="btn btn-ghost btn-sm" href={`/vehicule/${vehicle.id}/istoric`}>
            <Icon name="history" size={16} /> {t('Istoric service')}
          </Link>
          <Link className="btn btn-sm" href={`/vehicule/${vehicle.id}/scadente/nou`}>
            <Icon name="plus" size={16} /> {t('Adaugă / actualizează scadență')}
          </Link>
        </div>
      </header>

      <section className="panel" style={{ marginBottom: 'var(--s4)' }}>
        <div className="panel-head">
          <span className="panel-title">{t('Identificare')}</span>
        </div>
        <div className="panel-body stack">
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
      </section>

      <section className="panel">
        <div className="panel-head">
          <span className="panel-title">{t('Scadențe')}</span>
        </div>
        <div className="panel-body-flush">
        {TYPES.map(({ type, label }) => {
          const d = byType.get(type);
          return (
            <div key={type} className="deadline-row">
              <div className="list-row">
                <div>
                  <strong>{t(label)}</strong>
                  <div className="muted" style={{ fontSize: 'var(--text-sm)' }}>
                    {d
                      ? `${d.expiresAt ?? '—'} · ${daysLeftText(t, d.daysLeft)}${d.verified ? t(' · validat') : ''}`
                      : t('neintrodus')}
                  </div>
                  {type === 'ITP' ? (
                    <a
                      href="https://prog.rarom.ro/rarpol/"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <Icon name="external" size={16} /> {t('Verificare ITP (RAR)')}
                    </a>
                  ) : null}
                  {type === 'RCA' ? (
                    <a
                      href="https://www.aida.info.ro/polite-rca"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <Icon name="external" size={16} /> {t('Verificare RCA (AIDA)')}
                    </a>
                  ) : null}
                  {type === 'ROAD_TAX' ? (
                    <a
                      href="https://www.erovinieta.ro"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      <Icon name="external" size={16} /> {t('Verificare taxă de drum (eRovinieta)')}
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
      </section>
      <p className="muted" style={{ fontSize: 'var(--text-sm)', marginTop: 'var(--s4)' }}>
        {t('Stările se calculează pe baza datelor introduse și validate; aplicația nu interoghează baze oficiale.')}
      </p>

      <BottomNav />
    </>
  );
}
