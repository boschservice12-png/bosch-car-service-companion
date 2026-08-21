'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useT } from '@/lib/i18n';
import { Icon, type IconName } from '@/components/Icon';
import { Logo } from '@/components/Logo';

/**
 * Application navigation.
 *
 * Every destination lives here. They used to be split: four in a bottom bar and
 * eight more as tiles in the middle of the home page — which is what made the
 * dashboard read as a menu, because navigation was occupying the canvas where
 * data belongs.
 *
 * On a phone this stays a bottom bar with the four primary destinations; the
 * rest are reachable from their sections. On desktop it is the console sidebar.
 *
 * The component name is unchanged so the 20-odd pages that already render
 * <BottomNav /> pick up the new chrome without being touched.
 */

interface NavItem {
  href: string;
  label: string;
  icon: IconName;
  /** Primary items appear in the phone bottom bar; the rest are desktop-only. */
  primary?: boolean;
}

const MAIN: NavItem[] = [
  { href: '/', label: 'Acasă', icon: 'home', primary: true },
  { href: '/vehicule', label: 'Vehicule', icon: 'car', primary: true },
  { href: '/alerte', label: 'Alerte', icon: 'bell' },
  { href: '/mesaje', label: 'Mesaje', icon: 'message', primary: true },
];

const SERVICES: NavItem[] = [
  { href: '/oferte', label: 'Cere ofertă', icon: 'wrench' },
  { href: '/asistenta', label: 'Asistență rutieră', icon: 'sos' },
  { href: '/mobilitate', label: 'Mobilitate', icon: 'taxi' },
  { href: '/taxe', label: 'Taxe și impozite', icon: 'receipt' },
];

const ACCOUNT: NavItem[] = [{ href: '/profil', label: 'Profil', icon: 'user', primary: true }];

export function BottomNav() {
  const path = usePathname();
  const t = useT();

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
          <span className="nav-brand-name">Companion</span>
          <span className="nav-brand-sub">Bosch Car Service</span>
        </span>
      </Link>

      {MAIN.map(link)}

      <span className="nav-secondary">
        <span className="nav-group-label">{t('Servicii')}</span>
        {SERVICES.map(link)}
        <span className="nav-group-label">{t('Profil')}</span>
      </span>

      {ACCOUNT.map(link)}
    </nav>
  );
}
