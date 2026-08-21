/**
 * Line icons, drawn on a 24px grid with a 1.75 stroke so they sit at the same
 * optical weight as Barlow's semibold.
 *
 * These replace the emoji the interface used as iconography (🚗 ➕ 🔔 🧰 …).
 * Emoji render differently on every platform, cannot inherit colour, are
 * announced verbosely by screen readers, and read as a prototype.
 *
 * Icons here are decorative: every one is paired with a visible text label, so
 * they carry `aria-hidden` and add nothing to the accessibility tree.
 */

export type IconName =
  | 'car'
  | 'plus'
  | 'bell'
  | 'wrench'
  | 'message'
  | 'sos'
  | 'taxi'
  | 'clipboard'
  | 'receipt'
  | 'user'
  | 'home'
  | 'history'
  | 'document'
  | 'shield'
  | 'chevron'
  | 'external'
  | 'check'
  | 'alert'
  | 'paperclip'
  | 'download'
  | 'trash'
  | 'edit'
  | 'key'
  | 'phone'
  | 'import'
  | 'mail'
  | 'close'
  | 'arrow-left'
  | 'dot';

const PATHS: Record<IconName, React.ReactNode> = {
  car: (
    <>
      <path d="M5 17h14M6.5 17v1.5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V17M20.5 17v1.5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V17" />
      <path d="M3.5 17v-4.2a2 2 0 0 1 .2-.9l1.9-3.7A2 2 0 0 1 7.4 7h9.2a2 2 0 0 1 1.8 1.2l1.9 3.7c.13.28.2.58.2.9V17" />
      <path d="M3.7 12.2h16.6" />
      <circle cx="7.5" cy="14.5" r="1" />
      <circle cx="16.5" cy="14.5" r="1" />
    </>
  ),
  plus: <path d="M12 5v14M5 12h14" />,
  bell: (
    <>
      <path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9Z" />
      <path d="M10 18a2 2 0 0 0 4 0" />
    </>
  ),
  wrench: <path d="M15.5 4.5a4.5 4.5 0 0 0-5.9 5.6L4 15.7 8.3 20l5.6-5.6a4.5 4.5 0 0 0 5.6-5.9l-2.8 2.8-2.4-.6-.6-2.4 2.8-2.8Z" />,
  message: <path d="M4 5h16v11H9l-5 4V5Z" />,
  sos: (
    <>
      <path d="M12 3.5 3.5 19h17L12 3.5Z" />
      <path d="M12 10v4M12 16.5v.01" />
    </>
  ),
  taxi: (
    <>
      <path d="M4 17h16M5.5 17v1.5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V17M21.5 17v1.5a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1V17" />
      <path d="M3.5 17v-4a2 2 0 0 1 .2-.9l1.7-3.4A2 2 0 0 1 7.2 7.6h9.6a2 2 0 0 1 1.8 1.1l1.7 3.4c.13.28.2.58.2.9v4" />
      <path d="M9.5 7.6V5.5h5v2.1" />
    </>
  ),
  clipboard: (
    <>
      <path d="M9 4.5h6v2H9zM7 5.5H5.5v14h13v-14H17" />
      <path d="M8.5 11h7M8.5 14.5h4.5" />
    </>
  ),
  receipt: (
    <>
      <path d="M6 3.5h12v17l-2-1.4-2 1.4-2-1.4-2 1.4-2-1.4-2 1.4v-17Z" />
      <path d="M9 8.5h6M9 12.5h6" />
    </>
  ),
  user: (
    <>
      <circle cx="12" cy="8.5" r="3.5" />
      <path d="M5 20a7 7 0 0 1 14 0" />
    </>
  ),
  home: <path d="M4 10.5 12 4l8 6.5V20H4v-9.5Z" />,
  history: (
    <>
      <path d="M4 12a8 8 0 1 0 2.5-5.8" />
      <path d="M4 4.5V9h4.5" />
      <path d="M12 8v4.5l3 1.8" />
    </>
  ),
  document: (
    <>
      <path d="M6 3.5h7l5 5v12H6v-17Z" />
      <path d="M13 3.5v5h5" />
    </>
  ),
  shield: <path d="M12 3.5 5 6v6c0 4.2 2.9 7.4 7 8.5 4.1-1.1 7-4.3 7-8.5V6l-7-2.5Z" />,
  chevron: <path d="M9.5 5.5 16 12l-6.5 6.5" />,
  external: (
    <>
      <path d="M14 4.5h5.5V10" />
      <path d="M19 5 11 13" />
      <path d="M18 14.5v5H4.5V6h5" />
    </>
  ),
  check: <path d="M5 12.5 10 17.5 19 7" />,
  alert: (
    <>
      <circle cx="12" cy="12" r="8.5" />
      <path d="M12 7.5v5M12 15.5v.01" />
    </>
  ),
  paperclip: <path d="M17.5 9.5 10.7 16.3a2.6 2.6 0 0 1-3.7-3.7l7.3-7.3a4 4 0 0 1 5.7 5.7l-7.3 7.3a5.5 5.5 0 0 1-7.8-7.8l6.6-6.6" />,
  download: (
    <>
      <path d="M12 4v10" />
      <path d="M8 10.5 12 14.5l4-4" />
      <path d="M5 19h14" />
    </>
  ),
  trash: (
    <>
      <path d="M4.5 7h15" />
      <path d="M9.5 7V5h5v2" />
      <path d="M6.5 7l.8 12h9.4l.8-12" />
      <path d="M10.5 10.5v5M13.5 10.5v5" />
    </>
  ),
  edit: (
    <>
      <path d="M4 20h4l10-10a2.1 2.1 0 0 0-3-3L5 17v3Z" />
      <path d="M14.5 6.5 17.5 9.5" />
    </>
  ),
  key: (
    <>
      <circle cx="8" cy="12" r="3.5" />
      <path d="M11.5 12H20M17 12v3M14.5 12v2.5" />
    </>
  ),
  phone: <path d="M6.5 4h3l1.5 4-2 1.5a11 11 0 0 0 5.5 5.5L16 13l4 1.5v3a2 2 0 0 1-2.2 2A15.5 15.5 0 0 1 4.5 6.2 2 2 0 0 1 6.5 4Z" />,
  import: (
    <>
      <path d="M12 15V4" />
      <path d="M8 11.5 12 15.5l4-4" />
      <path d="M4.5 15v3.5h15V15" />
    </>
  ),
  mail: (
    <>
      <path d="M3.5 6h17v12h-17z" />
      <path d="m3.5 7 8.5 6 8.5-6" />
    </>
  ),
  dot: <circle cx="12" cy="12" r="3.5" fill="currentColor" stroke="none" />,
  close: <path d="M6 6l12 12M18 6 6 18" />,
  'arrow-left': (
    <>
      <path d="M19 12H5" />
      <path d="m10.5 6.5-5.5 5.5 5.5 5.5" />
    </>
  ),
};

export function Icon({
  name,
  size = 20,
  className,
}: {
  name: IconName;
  size?: number;
  className?: string;
}) {
  return (
    <svg
      className={className}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.75}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      {PATHS[name]}
    </svg>
  );
}
