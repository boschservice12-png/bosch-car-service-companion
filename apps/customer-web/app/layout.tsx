import type { Metadata, Viewport } from 'next';
import './globals.css';
import { LangSwitcher, LocaleProvider } from '@/lib/i18n';

export const metadata: Metadata = {
  title: 'Bosch Car Service Companion',
  description: 'Aplicația clienților SC Szkaliczki Service SRL',
  manifest: '/manifest.webmanifest',
};

export const viewport: Viewport = {
  themeColor: '#0a2540',
  width: 'device-width',
  initialScale: 1,
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ro">
      <body>
        <LocaleProvider>
          <LangSwitcher />
          <div className="app-shell">{children}</div>
        </LocaleProvider>
      </body>
    </html>
  );
}
