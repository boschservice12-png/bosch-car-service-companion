'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type TaxItem, type PaymentStatus } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<PaymentStatus, string> = {
  UNPAID: 'badge-warn',
  PARTIALLY_PAID: 'badge-warn',
  PAID: 'badge-ok',
  OVERDUE: 'badge-err',
};

function money(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

export default function TaxListPage() {
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
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [router]);

  useEffect(load, [load]);

  return (
    <>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>{t('Taxe și impozite')}</h1>
        <Link className="btn" style={{ width: 'auto', padding: '8px 12px' }} href="/taxe/nou">
          {t('+ Taxă')}
        </Link>
      </div>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={2} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title={t('Nicio taxă')} hint={t('Adăugați taxele și impozitele anuale pentru a le urmări plata.')} />
      ) : null}

      {items && items.length > 0 ? (
        <div className="stack" style={{ gap: 10 }}>
          {items.map((tax) => (
            <Link key={tax.id} href={`/taxe/${tax.id}`} className="card" style={{ textDecoration: 'none', color: 'inherit' }}>
              <div className="list-row">
                <div>
                  <strong>{t(tax.typeLabel)} · {tax.year}</strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {money(tax.amount)}
                    {tax.dueDate ? t(' · scadent {date}', { date: tax.dueDate }) : ''}
                  </div>
                </div>
                <span className={`badge ${STATUS_CLASS[tax.status]}`}>{t(tax.statusLabel)}</span>
              </div>
            </Link>
          ))}
        </div>
      ) : null}

      <BottomNav />
    </>
  );
}
