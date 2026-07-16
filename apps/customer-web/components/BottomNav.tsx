'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const items = [
  { href: '/', label: 'Acasă', icon: '🏠' },
  { href: '/vehicule', label: 'Vehicule', icon: '🚗' },
  { href: '/mesaje', label: 'Mesaje', icon: '💬' },
  { href: '/profil', label: 'Profil', icon: '👤' },
];

export function BottomNav() {
  const path = usePathname();
  return (
    <nav className="bottom-nav" aria-label="Navigație principală">
      {items.map((it) => {
        const active = it.href === '/' ? path === '/' : path.startsWith(it.href);
        return (
          <Link key={it.href} href={it.href} className={active ? 'active' : ''} aria-current={active ? 'page' : undefined}>
            <span aria-hidden>{it.icon}</span>
            <span>{it.label}</span>
          </Link>
        );
      })}
    </nav>
  );
}
