'use client';

import type { Deadline, DeadlineState } from '@/lib/types';
import { useT } from '@/lib/i18n';

/**
 * The validity meter — the product's signature element.
 *
 * A deadline is not a status, it is a window: it starts at `validFrom` and ends
 * at `expiresAt`. A coloured pill said only "warn"; it never said how close.
 * The bar shows how much of the legal window has been consumed, and the number
 * that actually matters — days remaining — is set large in the condensed face.
 *
 * Accessibility: colour is never the only signal. The large numeral, the label
 * beneath it and the `aria-label` all state the same thing in words, which is
 * also what keeps this WCAG 2.1 AA compliant when the palette is unavailable.
 */

const STATE_CLASS: Record<DeadlineState, string> = {
  VALID: 'meter-ok',
  DUE_SOON: 'meter-warn',
  EXPIRED: 'meter-err',
  UNKNOWN: 'meter-unknown',
};

/**
 * How much of the validity window has elapsed, 0..1.
 *
 * Falls back to a sensible fill when `validFrom` is missing — which happens
 * often, because customers may enter only an expiry date. In that case we
 * assume a one-year window rather than showing an empty bar, and an expired
 * deadline always reads as full.
 */
function consumedFraction(validFrom: string | null, expiresAt: string | null, daysLeft: number | null): number {
  if (daysLeft === null || expiresAt === null) return 0;
  if (daysLeft <= 0) return 1;

  const end = Date.parse(expiresAt);
  const start = validFrom ? Date.parse(validFrom) : NaN;
  const totalDays = Number.isNaN(start) ? 365 : Math.max(1, (end - start) / 86_400_000);

  return Math.min(1, Math.max(0, 1 - daysLeft / totalDays));
}

export function ValidityMeter({
  deadline,
  hero = false,
}: {
  deadline: Pick<Deadline, 'typeLabel' | 'state' | 'stateLabel' | 'validFrom' | 'expiresAt' | 'daysLeft'>;
  /** The hero variant is used once per screen, for the most urgent deadline. */
  hero?: boolean;
}) {
  const t = useT();
  const { typeLabel, state, stateLabel, validFrom, expiresAt, daysLeft } = deadline;

  const pct = Math.round(consumedFraction(validFrom, expiresAt, daysLeft) * 100);

  // The countdown wording already exists in all three dictionaries.
  let countLabel: string;
  let countValue: string;
  if (daysLeft === null) {
    countValue = '—';
    countLabel = t('fără dată');
  } else if (daysLeft < 0) {
    // The minus is not decoration. Without it the hero read "9 zile" for a
    // deadline that expired nine days ago — the largest element on the screen
    // stating the opposite of the truth, while the small print below it said
    // "expirat de 9 zile". A leading minus makes the count unambiguous and
    // needs no new copy in any of the three languages.
    countValue = `−${Math.abs(daysLeft)}`;
    countLabel = t('expirat de {n} zile', { n: Math.abs(daysLeft) });
  } else if (daysLeft === 0) {
    countValue = '0';
    countLabel = t('expiră azi');
  } else {
    countValue = String(daysLeft);
    countLabel = t('{n} zile rămase', { n: daysLeft });
  }

  return (
    <div
      className={`meter ${STATE_CLASS[state]}${hero ? ' meter-hero' : ''}`}
      role="group"
      aria-label={`${t(typeLabel)} — ${t(stateLabel)}, ${countLabel}`}
    >
      <span className="meter-label">{t(typeLabel)}</span>

      <span className="meter-count">
        {countValue}
        {daysLeft !== null && (
          <span className="meter-count-unit">{t('zile')}</span>
        )}
      </span>

      <span className="meter-track">
        <span className="meter-fill" style={{ width: `${daysLeft === null ? 0 : pct}%` }} />
      </span>

      <span className="meter-foot">
        <span>{countLabel}</span>
        <span>{t(stateLabel)}</span>
      </span>
    </div>
  );
}

/**
 * Row-scale variant, for tables.
 *
 * Same idea as the hero — a consumed-window bar plus the day count — reduced to
 * fit a dense row. The state word travels with it so colour is never the only
 * signal, and the count keeps the leading minus when a deadline has passed.
 */
export function InlineMeter({
  deadline,
}: {
  deadline: Pick<Deadline, 'state' | 'stateLabel' | 'validFrom' | 'expiresAt' | 'daysLeft'>;
}) {
  const t = useT();
  const { state, stateLabel, validFrom, expiresAt, daysLeft } = deadline;
  const pct = Math.round(consumedFraction(validFrom, expiresAt, daysLeft) * 100);

  const fill =
    state === 'VALID' ? 'var(--ok)'
    : state === 'DUE_SOON' ? 'var(--warn)'
    : state === 'EXPIRED' ? 'var(--accent)'
    : 'var(--border-strong)';

  const days =
    daysLeft === null ? '—' : daysLeft < 0 ? `−${Math.abs(daysLeft)}` : String(daysLeft);

  return (
    <span className={`meter-inline ${STATE_CLASS[state]}`}>
      <span className="meter-inline-days" style={{ color: fill }}>
        {days}
      </span>
      <span className="meter-inline-track">
        <span
          className="meter-inline-fill"
          style={{ width: `${daysLeft === null ? 0 : pct}%`, background: fill }}
        />
      </span>
      <span className="muted" style={{ minWidth: '11ch', fontSize: 'var(--text-xs)' }}>
        {t(stateLabel)}
      </span>
    </span>
  );
}
