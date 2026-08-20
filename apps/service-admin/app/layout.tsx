import type { Metadata, Viewport } from 'next';
import { Barlow, Barlow_Condensed } from 'next/font/google';
import './globals.css';
import { LangSwitcher, LocaleProvider } from '@/lib/i18n';
import { AdminNav } from '@/components/AdminNav';

/**
 * The same two faces as the customer app. `latin-ext` is required, not
 * optional: the portal is trilingual and needs Romanian ș/ț (comma below) and
 * Hungarian ő/ű (double acute), which a plain latin subset omits.
 */
const barlow = Barlow({
  subsets: ['latin', 'latin-ext'],
  weight: ['400', '500', '600', '700'],
  variable: '--font-barlow',
  display: 'swap',
});

const barlowCondensed = Barlow_Condensed({
  subsets: ['latin', 'latin-ext'],
  weight: ['500', '600', '700'],
  variable: '--font-barlow-condensed',
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'Portal Service — Bosch Car Service Companion',
  description: 'Portal de administrare pentru service',
  icons: {
    icon: [
      { url: '/icons/icon.svg', type: 'image/svg+xml' },
      { url: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
    ],
    apple: '/icons/apple-touch-icon.png',
  },
};

export const viewport: Viewport = { themeColor: '#0a2540', width: 'device-width', initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ro" className={`${barlow.variable} ${barlowCondensed.variable}`}>
      <body>
        <LocaleProvider>
          <LangSwitcher />
          <AdminNav />
          <div className="app-shell">{children}</div>
        </LocaleProvider>
      </body>
    </html>
  );
}
