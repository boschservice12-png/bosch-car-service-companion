import type { DeadlineState } from '@/lib/types';
import { Icon, type IconName } from '@/components/Icon';

const MAP: Record<DeadlineState, { cls: string; icon: IconName }> = {
  VALID: { cls: 'badge-ok', icon: 'check' },
  DUE_SOON: { cls: 'badge-warn', icon: 'alert' },
  EXPIRED: { cls: 'badge-err', icon: 'close' },
  UNKNOWN: { cls: 'badge-unknown', icon: 'dot' },
};

/** Status = text + icon + colour. Colour is never the only signal (WCAG 2.1 AA). */
export function DeadlineBadge({ state, label }: { state: DeadlineState; label: string }) {
  const { cls, icon } = MAP[state];
  return (
    <span className={`badge ${cls}`}>
      <Icon name={icon} size={13} /> {label}
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
