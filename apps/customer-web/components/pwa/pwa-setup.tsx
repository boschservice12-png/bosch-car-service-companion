'use client';

import { useEffect } from 'react';
import { InstallCompanion } from './install-companion';
import { IosInstallGuide } from './ios-install-guide';

/**
 * Punctul unic de montare PWA (în layout): înregistrează service worker-ul
 * minimal de offline și afișează panoul de instalare potrivit platformei.
 */
export function PwaSetup() {
  useEffect(() => {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/sw.js').catch(() => {
        // Fără SW (browser vechi/mod privat) — aplicația merge normal, doar
        // fără pagina de „fără conexiune".
      });
    }
  }, []);

  return (
    <>
      <InstallCompanion />
      <IosInstallGuide />
    </>
  );
}
