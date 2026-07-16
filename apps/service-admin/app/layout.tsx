import type { Metadata, Viewport } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Portal Service — Bosch Car Service Companion',
  description: 'Portal de administrare pentru service',
};

export const viewport: Viewport = { themeColor: '#0a2540', width: 'device-width', initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ro">
      <body>
        <div className="app-shell">{children}</div>
      </body>
    </html>
  );
}
