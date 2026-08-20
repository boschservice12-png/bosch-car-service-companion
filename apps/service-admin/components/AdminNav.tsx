'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useT } from '@/lib/i18n';
import { Icon, type IconName } from '@/components/Icon';
import { Logo } from '@/components/Logo';

/**
 * Portal navigation.
 *
 * Rendered once from the root layout rather than by each of the nineteen
 * pages, which is why it decides for itself whether to appear: the sign-in
 * screen has no navigation, and neither does anything else reachable while
 * signed out.
 *
 * Grouping follows how the counter actually works — the fleet is what staff
 * open all day, the request queues are what they answer, and the last group is
 * housekeeping they touch rarely.
 */

interface NavItem {
  href: string;
  label: string;
  icon: IconName;
  /** Primary items stay in the phone bottom bar; the rest are desktop-only. */
  primary?: boolean;
}

const FLEET: NavItem[] = [
  { href: '/', label: 'Vehicule', icon: 'car', primary: true },
  { href: '/mesaje', label: 'Mesaje', icon: 'message', primary: true },
  { href: '/notificari', label: 'Notificări', icon: 'bell', primary: true },
];

const REQUESTS: NavItem[] = [
  { href: '/oferte', label: 'Cereri ofertă', icon: 'wrench' },
  { href: '/asistenta', label: 'Asistență rutieră', icon: 'sos' },
  { href: '/mobilitate', label: 'Mobilitate', icon: 'taxi' },
  { href: '/daune', label: 'Dosare de daună', icon: 'clipboard' },
  { href: '/taxe', label: 'Taxe și impozite', icon: 'receipt' },
];

const ADMIN: NavItem[] = [
  { href: '/import', label: 'Import clienți', icon: 'import' },
  { href: '/securitate', label: 'Securitate', icon: 'shield', primary: true },
];

/** Screens that exist outside the portal chrome. */
const CHROMELESS = ['/login'];

export function AdminNav() {
  const path = usePathname();
  const t = useT();

  if (CHROMELESS.some((p) => path === p || path.startsWith(`${p}/`))) return null;

  const isActive = (href: string) => (href === '/' ? path === '/' : path.startsWith(href));

  const link = (it: NavItem) => {
    const active = isActive(it.href);
    return (
      <Link
        key={it.href}
        href={it.href}
        className={`${active ? 'active' : ''}${it.primary ? '' : ' nav-desktop-only'}`}
        aria-current={active ? 'page' : undefined}
      >
        <Icon name={it.icon} size={19} />
        <span>{t(it.label)}</span>
      </Link>
    );
  };

  return (
    <nav className="nav" aria-label={t('Navigație principală')}>
      <Link href="/" className="nav-brand">
        <Logo size={32} className="nav-brand-mark" />
        <span className="nav-brand-text">
          <span className="nav-brand-name">Portal Service</span>
          <span className="nav-brand-sub">Bosch Car Service</span>
        </span>
      </Link>

      {FLEET.map(link)}

      <span className="nav-secondary">
        <span className="nav-group-label">{t('Cereri')}</span>
        {REQUESTS.map(link)}
        <span className="nav-group-label">{t('Administrare')}</span>
        {ADMIN.filter((i) => i.href === '/import').map(link)}
      </span>

      {ADMIN.filter((i) => i.href === '/securitate').map(link)}
    </nav>
  );
}
