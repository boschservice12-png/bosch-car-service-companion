'use client';

import type { ReactNode } from 'react';
import { useT } from '@/lib/i18n';

export function Loading({ rows = 3 }: { rows?: number }) {
  const t = useT();
  return (
    <div aria-busy="true" aria-label={t('Se încarcă')}>
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="skeleton" />
      ))}
    </div>
  );
}

export function EmptyState({ title, hint, action }: { title: string; hint?: string; action?: ReactNode }) {
  return (
    <div className="card center stack">
      <strong>{title}</strong>
      {hint ? <p className="muted">{hint}</p> : null}
      {action}
    </div>
  );
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  const t = useT();
  return (
    <div className="alert alert-err" role="alert">
      <div>{message}</div>
      {onRetry ? (
        <button className="btn btn-ghost" style={{ marginTop: 10 }} onClick={onRetry}>
          {t('Reîncearcă')}
        </button>
      ) : null}
    </div>
  );
}
