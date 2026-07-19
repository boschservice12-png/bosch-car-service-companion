'use client';

import Link from 'next/link';

/**
 * Deschiderea dosarului de daună se face EXCLUSIV prin platforma oficială
 * amiabila.com — aplicația doar conectează clientul. Dosarele deschise apar
 * apoi în lista „Dosar de daună", unde service-ul le urmărește starea.
 */
export default function NewDamageClaimPage() {
  return (
    <>
      <Link href="/daune" className="muted">
        ← Dosar de daună
      </Link>
      <h1>Deschide un dosar de daună</h1>

      <div className="card stack" style={{ gap: 10 }}>
        <strong>📋 Dosarul se deschide prin amiabila.com</strong>
        <span className="muted" style={{ fontSize: '0.85rem' }}>
          Constatarea amiabilă și dosarul de daună se completează pe platforma oficială — aplicația doar vă
          conectează. Dosarul dumneavoastră apare apoi aici, iar service-ul îi urmărește starea.
        </span>
        <a
          className="btn"
          href="https://amiabila.com/"
          target="_blank"
          rel="noopener noreferrer"
          style={{ textAlign: 'center', textDecoration: 'none' }}
        >
          Deschide dosarul pe amiabila.com ↗
        </a>
      </div>
    </>
  );
}
