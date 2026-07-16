'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type ServiceRecord } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';
import { ServiceRecordView } from '@/components/ServiceRecordView';

export default function VehicleServiceHistoryPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [records, setRecords] = useState<ServiceRecord[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .serviceRecords(params.id)
      .then(setRecords)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        if (err instanceof ApiError && (err.httpStatus === 403 || err.httpStatus === 404)) {
          setError('Istoricul nu este disponibil sau vehiculul nu vă aparține.');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  if (error) return <ErrorState message={error} onRetry={load} />;
  if (records === null) return <Loading rows={3} />;

  return (
    <>
      <Link href={`/vehicule/${params.id}`} className="muted">
        ← Vehicul
      </Link>
      <h1>Istoric service</h1>

      {records.length === 0 ? (
        <EmptyState title="Nicio intrare în istoric" hint="Service-ul va publica aici lucrările efectuate." />
      ) : (
        <div className="stack" style={{ gap: 12 }}>
          {records.map((r) => (
            <ServiceRecordView key={r.id} record={r} />
          ))}
        </div>
      )}

      <p className="muted" style={{ fontSize: '0.82rem' }}>
        Istoricul este publicat de service. Corecțiile apar ca intrări separate, păstrând înregistrarea originală.
      </p>

      <BottomNav />
    </>
  );
}
