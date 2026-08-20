/**
 * The Companion mark.
 *
 * Built from the existing favicon — a solid gear ring with a red hub on navy —
 * because that shape already reads correctly as "workshop" at any size. The
 * outline is generated rather than eyeballed: eight teeth, tips at r=22, roots
 * at r=17.8, with a 3° fillet ramping between them so the teeth are fused to
 * the ring instead of floating off it.
 *
 * Two earlier attempts are worth recording so they are not retried. Thin radial
 * ticks around the hub read as a rifle scope, not a gear, and vanished below
 * 32px. A red "validity" arc inside the ring band merged with the red hub into
 * a single blob. The mark does not need to restate the product's idea — the
 * validity meter already does that on every screen — so the gear stands alone.
 *
 * Colour: the gear takes `currentColor`, so it is white in the dark sidebar and
 * navy on light. The hub and the arc stay Bosch red.
 */

const GEAR =
  'M 19.54 6.77 L 19.61 2.44 A 22.0 22.0 0 0 1 28.39 2.44 L 28.46 6.77 A 17.8 17.8 0 0 1 33.03 8.66 ' +
  'L 36.14 5.65 A 22.0 22.0 0 0 1 42.35 11.86 L 39.34 14.97 A 17.8 17.8 0 0 1 41.23 19.54 ' +
  'L 45.56 19.61 A 22.0 22.0 0 0 1 45.56 28.39 L 41.23 28.46 A 17.8 17.8 0 0 1 39.34 33.03 ' +
  'L 42.35 36.14 A 22.0 22.0 0 0 1 36.14 42.35 L 33.03 39.34 A 17.8 17.8 0 0 1 28.46 41.23 ' +
  'L 28.39 45.56 A 22.0 22.0 0 0 1 19.61 45.56 L 19.54 41.23 A 17.8 17.8 0 0 1 14.97 39.34 ' +
  'L 11.86 42.35 A 22.0 22.0 0 0 1 5.65 36.14 L 8.66 33.03 A 17.8 17.8 0 0 1 6.77 28.46 ' +
  'L 2.44 28.39 A 22.0 22.0 0 0 1 2.44 19.61 L 6.77 19.54 A 17.8 17.8 0 0 1 8.66 14.97 ' +
  'L 5.65 11.86 A 22.0 22.0 0 0 1 11.86 5.65 L 14.97 8.66 A 17.8 17.8 0 0 1 19.54 6.77 Z';

const HOLE =
  'M 35.60 24.00 A 11.6 11.6 0 1 0 12.40 24.00 A 11.6 11.6 0 1 0 35.60 24.00 Z';

export function Logo({
  size = 32,
  className,
  title,
}: {
  size?: number;
  className?: string;
  /** Supply only when the mark stands alone; in a lockup the wordmark names it. */
  title?: string;
}) {
  return (
    <svg
      className={className}
      width={size}
      height={size}
      viewBox="0 0 48 48"
      role={title ? 'img' : undefined}
      aria-label={title}
      aria-hidden={title ? undefined : true}
      focusable="false"
    >
      {/* Gear body: outline with the hub hole cut out by the even-odd rule. */}
      <path d={`${GEAR} ${HOLE}`} fill="currentColor" fillRule="evenodd" />

      {/* Hub — the one element carried over unchanged from the favicon. */}
      <circle cx="24" cy="24" r="6.4" fill="#e2231a" />
    </svg>
  );
}
