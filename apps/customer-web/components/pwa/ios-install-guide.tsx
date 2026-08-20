'use client';

import { useEffect, useState } from 'react';
import { Icon } from '@/components/Icon';
import { useT } from '@/lib/i18n';
import { isIosSafari, isSmallScreen } from './device-detection';
import { isRecentlyDismissed, isStandalone, rememberDismissed } from './pwa-status';

/**
 * Ghid de instalare pentru iPhone/iPad: Safari nu are beforeinstallprompt,
 * deci arătăm pașii manuali („Adăugați la ecranul principal"). Apare doar pe
 * iOS + Safari, pe ecran de telefon, când nu rulăm deja standalone, și
 * respectă aceeași pauză de 7 zile după închidere.
 */
export function IosInstallGuide() {
  const t = useT();
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    setVisible(isIosSafari() && isSmallScreen() && !isStandalone() && !isRecentlyDismissed());
  }, []);

  if (!visible) return null;

  function dismiss() {
    rememberDismissed();
    setVisible(false);
  }

  return (
    <div className="pwa-banner" role="dialog" aria-label={t('Instalează Companion pe ecranul telefonului')}>
      <div className="pwa-banner-text">
        <b>{t('Instalează Companion pe ecranul telefonului')}</b>
        <ol className="pwa-steps">
          <li>{t('Apasă butonul Partajare din Safari.')}</li>
          <li>{t('Selectează „Adăugați la ecranul principal”.')}</li>
          <li>{t('Confirmă apăsând „Adăugați”.')}</li>
        </ol>
      </div>
      <div className="pwa-banner-actions">
        <button type="button" className="btn btn-ghost" onClick={dismiss} aria-label={t('Închide')}>
          <Icon name="close" size={16} />
        </button>
      </div>
    </div>
  );
}
