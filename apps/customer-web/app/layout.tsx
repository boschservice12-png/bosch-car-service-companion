import type { Metadata, Viewport } from 'next';
import { Barlow, Barlow_Condensed } from 'next/font/google';
import './globals.css';
import { LangSwitcher, LocaleProvider } from '@/lib/i18n';
import { PwaSetup } from '@/components/pwa/pwa-setup';

/**
 * Barlow is a humanist grotesque drawn in the lineage of transport and signage
 * lettering — the right register for a vehicle product, and a deliberate move
 * away from `system-ui`, which reads as unstyled.
 *
 * `latin-ext` is required, not optional: the interface is trilingual and needs
 * Romanian ș/ț (comma below) and Hungarian ő/ű (double acute). A subset without
 * it would drop glyphs in two of the three languages.
 */
const barlow = Barlow({
  subsets: ['latin', 'latin-ext'],
  weight: ['400', '500', '600', '700'],
  variable: '--font-barlow',
  display: 'swap',
});

/** Condensed carries the data: plates, dates, and the day counts. */
const barlowCondensed = Barlow_Condensed({
  subsets: ['latin', 'latin-ext'],
  weight: ['500', '600', '700'],
  variable: '--font-barlow-condensed',
  display: 'swap',
});

export const metadata: Metadata = {
  title: 'Bosch Car Service Companion',
  description: 'Aplicația clienților SC Szkaliczki Service SRL',
  manifest: '/manifest.webmanifest',
  icons: {
    icon: '/icons/icon-192.png',
    apple: '/icons/apple-touch-icon.png',
  },
  appleWebApp: {
    capable: true,
    title: 'Companion',
    statusBarStyle: 'default',
  },
};

export const viewport: Viewport = {
  themeColor: '#0a2540',
  width: 'device-width',
  initialScale: 1,
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ro" className={`${barlow.variable} ${barlowCondensed.variable}`}>
      <body>
        <LocaleProvider>
          <LangSwitcher />
          <div className="app-shell">{children}</div>
          <PwaSetup />
        </LocaleProvider>
      </body>
    </html>
  );
}
