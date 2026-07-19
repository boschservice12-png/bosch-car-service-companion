'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Conversation, type ConversationStatus } from '@/lib/types';
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
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>Mesaje</h1>
        <Link className="btn" style={{ width: 'auto', padding: '8px 12px' }} href="/mesaje/nou">
          + Nou
        </Link>
      </div>

      {error ? <ErrorState message={error} onRetry={load} /> : null}
      {!error && items === null ? <Loading rows={3} /> : null}
      {!error && items?.length === 0 ? (
        <EmptyState title="Nicio conversație" hint="Trimiteți un mesaj sau o cerere de ofertă service-ului." />
      ) : null}

      {items && items.length > 0 ? (
        <div className="stack" style={{ gap: 10 }}>
          {items.map((c) => (
            <Link key={c.id} href={`/mesaje/${c.id}`} className="card" style={{ textDecoration: 'none', color: 'inherit' }}>
              <div className="list-row">
                <div>
                  <strong>💬 {c.subject}</strong>
                  <div className="muted" style={{ fontSize: '0.82rem' }}>
                    {c.lastMessagePreview ?? ''}
                  </div>
                </div>
                <span className={`badge ${STATUS_CLASS[c.status]}`}>{c.statusLabel}</span>
              </div>
            </Link>
          ))}
        </div>
      ) : null}

      <BottomNav />
    </>
  );
}
