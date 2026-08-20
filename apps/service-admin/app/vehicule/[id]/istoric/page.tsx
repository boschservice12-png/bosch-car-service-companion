'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type ServiceRecord, type ServiceRecordInput } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { ServiceRecordForm } from '@/components/ServiceRecordForm';
import { ServiceRecordAdminCard } from '@/components/ServiceRecordAdminCard';
import { useT } from '@/lib/i18n';

export default function AdminServiceHistoryPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [records, setRecords] = useState<ServiceRecord[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .serviceRecords(params.id)
      .then(setRecords)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  function create(data: ServiceRecordInput) {
    setError(null);
    setBusy(true);
    api
      .createServiceRecord(params.id, data)
      .then(() => {
        setCreating(false);
        load();
      })
      .catch((err) => setError(err instanceof ApiError ? err.problem.title : 'Creare eșuată.'))
      .finally(() => setBusy(false));
  }

  if (error && records === null) return <ErrorState message={t(error)} onRetry={load} />;
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
          {!creating ? (
            <button className="btn btn-sm" onClick={() => setCreating(true)}>
              {t('+ Adaugă')}
            </button>
          ) : null}
        </div>
      </header>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      {creating ? (
        <div className="stack" style={{ gap: 6 }}>
          <strong>{t('Înregistrare nouă (ciornă)')}</strong>
          <ServiceRecordForm submitLabel={t('Salvează ciorna')} busy={busy} onSubmit={create} onCancel={() => setCreating(false)} />
        </div>
      ) : null}

      {records.length === 0 && !creating ? <p className="muted">{t('Nicio înregistrare de service.')}</p> : null}

      <div className="stack" style={{ gap: 12 }}>
        {records.map((r) => (
          <ServiceRecordAdminCard key={r.id} record={r} onChanged={load} />
        ))}
      </div>

      <p className="muted" style={{ fontSize: 'var(--text-sm)' }}>
        {t('O înregistrare publicată nu poate fi rescrisă. Pentru modificări folosiți „Creează corecție" — atât originalul, cât și corecția rămân vizibile.')}
      </p>
    </>
  );
}
