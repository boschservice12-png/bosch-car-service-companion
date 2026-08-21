'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Conversation, type ConversationStatus } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading, EmptyState, ErrorState } from '@/components/states';

const STATUS_CLASS: Record<ConversationStatus, string> = {
  OPEN: 'badge-unknown',
  WAITING_CLIENT: 'badge-warn',
  WAITING_SERVICE: 'badge-warn',
  CLOSED: 'badge-ok',
};

export default function MessagesPage() {
  const router = useRouter();
  const t = useT();
  const [items, setItems] = useState<Conversation[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    api
      .conversations()
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
      <header className="page-head">
        <div>
          <h1>{t('Mesaje')}</h1>
        </div>
        <div className="page-head-actions">
          <Link className="btn btn-sm" href="/mesaje/nou">
            <Icon name="plus" size={16} />
            {t('Mesaj nou')}
          </Link>
        </div>
      </header>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={3} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title={t('Nicio conversație')} hint={t('Trimiteți un mesaj sau o cerere de ofertă service-ului.')} />
      ) : null}

      {items && items.length > 0 ? (
        <section className="panel">
          <div className="panel-body-flush">
            {items.map((c) => (
              <Link key={c.id} href={`/mesaje/${c.id}`} className="row-link">
                <span className="row-main">
                  <span className="row-title">{c.subject}</span>
                  <span className="row-sub">{c.lastMessagePreview ?? ''}</span>
                </span>
                <span className={`badge ${STATUS_CLASS[c.status]}`}>{t(c.statusLabel)}</span>
              </Link>
            ))}
          </div>
        </section>
      ) : null}

      <BottomNav />
    </>
  );
}
