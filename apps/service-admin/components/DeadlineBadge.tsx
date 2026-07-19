import type { DeadlineState } from '@/lib/types';

const MAP: Record<DeadlineState, { cls: string; icon: string }> = {
  VALID: { cls: 'badge-ok', icon: '✓' },
  DUE_SOON: { cls: 'badge-warn', icon: '!' },
  EXPIRED: { cls: 'badge-err', icon: '×' },
  UNKNOWN: { cls: 'badge-unknown', icon: '•' },
};

/** Status = text + icon + culoare (culoarea nu e singurul indicator — WCAG). */
export function DeadlineBadge({ state, label }: { state: DeadlineState; label: string }) {
  const { cls, icon } = MAP[state];
  return (
    <span className={`badge ${cls}`}>
      <span aria-hidden>{icon}</span> {label}
    </span>
  );
}

export function daysLeftText(
  t: (s: string, vars?: Record<string, string | number>) => string,
  daysLeft: number | null,
): string {
  if (daysLeft === null) return t('fără dată');
  if (daysLeft < 0) return t('expirat de {n} zile', { n: Math.abs(daysLeft) });
  if (daysLeft === 0) return t('expiră azi');
  return t('{n} zile rămase', { n: daysLeft });
}
