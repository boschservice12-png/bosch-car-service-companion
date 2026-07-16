'use client';

import { useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading, ErrorState } from '@/components/states';

export default function VehicleDetailPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [vehicle, setVehicle] = useState<Vehicle | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    api
      .vehicles()
      .then((list) => {
        if (!active) return;
        const found = list.find((v) => v.id === params.id) ?? null;
        if (!found) {
          setError('Vehiculul nu a fost găsit sau nu vă aparține.');
        }
        setVehicle(found);
      })
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
    return () => {
      active = false;
    };
  }, [params.id, router]);

  if (error) return <ErrorState message={error} />;
  if (!vehicle) return <Loading rows={2} />;

  const deadlines = [
    { label: 'ITP', href: `/vehicule/${vehicle.id}/itp` },
    { label: 'RCA', href: `/vehicule/${vehicle.id}/rca` },
    { label: 'Rovinietă', href: `/vehicule/${vehicle.id}/rovinieta` },
    { label: 'Asistență rutieră', href: `/vehicule/${vehicle.id}/asistenta` },
  ];

  return (
    <>
      <h1>{vehicle.plateNumber}</h1>
      <div className="card stack">
        <div>
          <span className="muted">VIN:</span> {vehicle.vin}
        </div>
        {vehicle.make ? (
          <div>
            <span className="muted">Vehicul:</span> {[vehicle.make, vehicle.model, vehicle.year].filter(Boolean).join(' ')}
          </div>
        ) : null}
      </div>

      <h2>Scadențe</h2>
      <div className="card">
        {deadlines.map((d) => (
          <div key={d.label} className="list-row">
            <span>{d.label}</span>
            <span className="badge badge-unknown">
              <span aria-hidden>•</span> Necunoscut
            </span>
          </div>
        ))}
      </div>
      <p className="muted">Introducerea și urmărirea scadențelor devin disponibile în modulul de scadențe (Sprint 2).</p>

      <BottomNav />
    </>
  );
}
