import type { Metadata, Viewport } from 'next';
import './globals.css';
import { LangSwitcher, LocaleProvider } from '@/lib/i18n';

export const metadata: Metadata = {
  title: 'Portal Service — Bosch Car Service Companion',
  description: 'Portal de administrare pentru service',
};

export const viewport: Viewport = { themeColor: '#0a2540', width: 'device-width', initialScale: 1 };

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
