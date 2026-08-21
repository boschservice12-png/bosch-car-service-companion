'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type TaxItem, type PaymentStatus } from '@/lib/types';
import { Loading, EmptyState, ErrorState } from '@/components/states';
import { useT } from '@/lib/i18n';

const STATUS_CLASS: Record<PaymentStatus, string> = {
  UNPAID: 'badge-warn',
  PARTIALLY_PAID: 'badge-warn',
  PAID: 'badge-ok',
  OVERDUE: 'badge-err',
};

function money(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

export default function AdminTaxListPage() {
  const router = useRouter();
  const t = useT();
  const [items, setItems] = useState<TaxItem[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .taxes()
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
          <h1>{t('Taxe și impozite')}</h1>
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
      {!error && items?.length === 0 ? <EmptyState title={t('Nicio taxă înregistrată')} /> : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((item) => (
              <Link key={item.id} href={`/taxe/${item.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{t(item.typeLabel)} · {item.year}</span>
                  <span className="row-sub">
                    {item.customerName ?? '—'} · {money(item.amount)}
                  </span>
                </span>
                <span className={`badge ${STATUS_CLASS[item.status]}`}>{t(item.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}
    </>
  );
}
