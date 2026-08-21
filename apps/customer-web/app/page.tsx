'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Deadline, type Me, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading } from '@/components/states';
import { Icon } from '@/components/Icon';
import { InlineMeter } from '@/components/ValidityMeter';

interface DeadlineRow {
  vehicle: Vehicle;
  deadline: Deadline;
}

/**
 * The customer dashboard.
 *
 * Content is data: a status summary, then every deadline as a dense row, then
 * the fleet. Navigation moved to the sidebar — it used to occupy this canvas as
 * eight tiles, which is what made the page read as a menu.
 *
 * Functionality is unchanged: same endpoints, same destinations, same
 * permissions. Only the arrangement differs.
 */
export default function HomePage() {
  const router = useRouter();
  const t = useT();
  const [me, setMe] = useState<Me | null>(null);
  const [vehicles, setVehicles] = useState<Vehicle[] | null>(null);
  const [rows, setRows] = useState<DeadlineRow[] | null>(null);

  const load = useCallback(() => {
    api
      .me()
      .then(async (profile) => {
        setMe(profile);
        const list = await api.vehicles();
        setVehicles(list);
        const perVehicle = await Promise.all(
          list.map(async (vehicle) => {
            const deadlines = await api.vehicleDeadlines(vehicle.id);
            return deadlines.map((deadline) => ({ vehicle, deadline }));
          }),
        );
        // Soonest first; anything already expired sorts to the top.
        setRows(
          perVehicle.flat().sort((a, b) => {
            const da = a.deadline.daysLeft ?? Number.MAX_SAFE_INTEGER;
            const db = b.deadline.daysLeft ?? Number.MAX_SAFE_INTEGER;
            return da - db;
          }),
        );
      })
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        setRows([]);
        setVehicles([]);
      });
  }, [router]);

  useEffect(load, [load]);

  if (!me || rows === null || vehicles === null) return <Loading rows={4} />;

  const expired = rows.filter((r) => r.deadline.state === 'EXPIRED').length;
  const dueSoon = rows.filter((r) => r.deadline.state === 'DUE_SOON').length;
  const valid = rows.filter((r) => r.deadline.state === 'VALID').length;

  return (
    <>
      <header className="page-head">
        <div>
          <p className="eyebrow">{t('Acasă')}</p>
          <h1>
            {t('Bună,')} {me.name ?? t('client')}
          </h1>
        </div>
        <div className="page-head-actions">
          <Link className="btn btn-ghost btn-sm" href="/vehicule/nou">
            <Icon name="plus" size={16} />
            {t('Adaugă un vehicul')}
          </Link>
          <Link className="btn btn-sm" href="/oferte/nou">
            <Icon name="wrench" size={16} />
            {t('Cere ofertă')}
          </Link>
        </div>
      </header>

      {/* ---- Status summary ------------------------------------------------ */}
      <div className="panel" style={{ marginBottom: 'var(--s4)' }}>
        <div className="stat-strip">
          <Link className="stat stat-err" href="/alerte">
            <span className="stat-value">{expired}</span>
            <span className="stat-label">{t('Expirat')}</span>
          </Link>
          <Link className="stat stat-warn" href="/alerte">
            <span className="stat-value">{dueSoon}</span>
            <span className="stat-label">{t('Expiră curând')}</span>
          </Link>
          <Link className="stat stat-ok" href="/alerte">
            <span className="stat-value">{valid}</span>
            <span className="stat-label">{t('Valabil')}</span>
          </Link>
        </div>
      </div>

      <div className="grid grid-main">
        {/* ---- Deadlines --------------------------------------------------- */}
        <section className="panel">
          <div className="panel-head">
            <span className="panel-title">{t('Alerte')}</span>
            <Link className="btn btn-quiet btn-sm" href="/alerte">
              {t('Vezi toate')}
              <Icon name="chevron" size={14} />
            </Link>
          </div>

          {rows.length === 0 ? (
            <div className="panel-body">
              <div className="empty">
                <Icon name="car" size={30} className="empty-icon" />
                <span className="empty-title">{t('Niciun vehicul încă.')}</span>
                <Link className="btn btn-sm" href="/vehicule/nou">
                  <Icon name="plus" size={16} />
                  {t('Adaugă un vehicul')}
                </Link>
              </div>
            </div>
          ) : (
            <div className="dtable-scroll">
            <table className="dtable">
              <thead>
                <tr>
                  <th>{t('Tip')}</th>
                  <th>{t('Vehicul')}</th>
                  <th>{t('Data expirării')}</th>
                  <th>{t('Stare')}</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {rows.map(({ vehicle, deadline }) => (
                  <tr key={deadline.id}>
                    <td>
                      <Link href={`/vehicule/${vehicle.id}`} style={{ fontWeight: 600 }}>
                        {t(deadline.typeLabel)}
                      </Link>
                    </td>
                    <td>
                      <Link href={`/vehicule/${vehicle.id}`} className="plate">
                        {vehicle.plateNumber}
                      </Link>
                    </td>
                    <td className="td-num muted">{deadline.expiresAt ?? '—'}</td>
                    <td style={{ minWidth: 200 }}>
                      <InlineMeter deadline={deadline} />
                    </td>
                    <td className="td-tight">
                      <Link href={`/vehicule/${vehicle.id}`} aria-label={t('Detalii')}>
                        <Icon name="chevron" size={15} className="muted" />
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            </div>
          )}
        </section>

        {/* ---- Fleet ------------------------------------------------------- */}
        <section className="panel">
          <div className="panel-head">
            <span className="panel-title">{t('Vehiculele mele')}</span>
            <Link className="btn btn-quiet btn-sm" href="/vehicule">
              {t('Vezi toate')}
              <Icon name="chevron" size={14} />
            </Link>
          </div>
          <div className="panel-body-flush">
            {vehicles.map((v) => {
              const worst = rows.find((r) => r.vehicle.id === v.id)?.deadline;
              return (
                <Link key={v.id} className="row-link" href={`/vehicule/${v.id}`}>
                  <span className="row-main">
                    <span className="row-title plate">{v.plateNumber}</span>
                    <span className="row-sub">
                      {[v.make, v.model].filter(Boolean).join(' ') || v.vin}
                    </span>
                  </span>
                  {worst && (
                    <span className={`dot dot-${tone(worst)}`} aria-hidden />
                  )}
                </Link>
              );
            })}
          </div>
        </section>
      </div>

      <BottomNav />
    </>
  );
}

function tone(d: Deadline): string {
  if (d.state === 'VALID') return 'ok';
  if (d.state === 'DUE_SOON') return 'warn';
  if (d.state === 'EXPIRED') return 'err';
  return 'unknown';
}
