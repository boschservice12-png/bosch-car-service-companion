'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type DamageClaim, type DamageClaimStatus } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

const STATUS_CLASS: Record<DamageClaimStatus, string> = {
  SUBMITTED: 'badge-warn',
  DOCUMENTS_MISSING: 'badge-warn',
  IN_REVIEW: 'badge-unknown',
  CONTACTED: 'badge-unknown',
  FILE_OPENED: 'badge-ok',
  CLOSED: 'badge-ok',
};

export default function AdminDamageClaimListPage() {
  const router = useRouter();
  const t = useT();
  const [items, setItems] = useState<DamageClaim[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .damageClaims()
      .then(setItems)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [router]);

  useEffect(load, [load]);

  return (
    <>
      <header className="page-head">
        <div>
          <h1>{t('Dosare de daună')}</h1>
        </div>
        <div className="page-head-actions">
          <Link href="/" className="back-link">
            <Icon name="arrow-left" size={14} />
          {t('Vehicule')}
          </Link>
        </div>
      </header>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? <EmptyState title={t('Niciun dosar de daună')} /> : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((c) => (
              <Link key={c.id} href={`/daune/${c.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{c.insurer ?? t('Dosar de daună')}</span>
                  <span className="row-sub">
                    {c.customerName ?? '—'}
                    {c.policyNumber ? ` · ${c.policyNumber}` : ''}
                    {c.incidentDate ? ` · ${c.incidentDate}` : ''}
                  </span>
                </span>
                <span className={`badge ${STATUS_CLASS[c.status]}`}>{t(c.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
