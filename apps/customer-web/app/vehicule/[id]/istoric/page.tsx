'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type ServiceRecord } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';
import { ServiceRecordView } from '@/components/ServiceRecordView';

export default function VehicleServiceHistoryPage() {
  const router = useRouter();
  const t = useT();
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

  if (error) return <ErrorState message={t(error)} onRetry={load} />;
  if (records === null) return <Loading rows={3} />;

  return (
    <>
      <header className="page-head">
        <div>
      <header className="page-head">
        <div>
  <Link href={`/vehicule/${params.id}`} className="back-link">
            <Icon name="arrow-left" size={14} />
            {t('Vehicul')}
          </Link>
          <h1>{t('Istoric service')}</h1>
        </div>
      </header>
        </div>
        <div className="page-head-actions">
          {records.length > 0 ? (
            <a className="btn btn-ghost btn-sm" href={`/api/vehicles/${params.id}/service-records/pdf`} target="_blank" rel="noopener">
              <Icon name="download" size={16} /> PDF
            </a>
          ) : null}
        </div>
      </header>

      {records.length === 0 ? (
        <EmptyState title={t('Nicio intrare în istoric')} hint={t('Service-ul va publica aici lucrările efectuate.')} />
      ) : (
        <div className="stack" style={{ gap: 12 }}>
          {records.map((r) => (
            <ServiceRecordView key={r.id} record={r} />
          ))}
        </div>
      )}

      <p className="muted" style={{ fontSize: 'var(--text-sm)' }}>
        {t('Istoricul este publicat de service. Corecțiile apar ca intrări separate, păstrând înregistrarea originală.')}
      </p>

      <BottomNav />
    </>
  );
}
