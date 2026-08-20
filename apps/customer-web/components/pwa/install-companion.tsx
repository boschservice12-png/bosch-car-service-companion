'use client';

import { useEffect, useState } from 'react';
import { Icon } from '@/components/Icon';
import { useT } from '@/lib/i18n';
import { isSmallScreen } from './device-detection';
import { isRecentlyDismissed, isStandalone, rememberDismissed } from './pwa-status';

/** Evenimentul Chrome `beforeinstallprompt` (netipizat în lib.dom). */
interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

/**
 * Panou de instalare pentru Android/Chrome: apare DOAR când browserul spune
 * că aplicația e instalabilă (beforeinstallprompt), nu rulează deja
 * standalone, ecranul e de telefon și clientul nu l-a închis în ultimele
 * 7 zile. Instalarea pornește numai la apăsarea butonului.
 */
export function InstallCompanion() {
  const t = useT();
  const [promptEvent, setPromptEvent] = useState<BeforeInstallPromptEvent | null>(null);
  const [hidden, setHidden] = useState(false);

  useEffect(() => {
    if (isStandalone() || isRecentlyDismissed() || !isSmallScreen()) return;

    const onPrompt = (e: Event) => {
      e.preventDefault(); // nu lăsăm mini-infobarul implicit; afișăm panoul nostru
      setPromptEvent(e as BeforeInstallPromptEvent);
    };
    const onInstalled = () => setPromptEvent(null);

    window.addEventListener('beforeinstallprompt', onPrompt);
    window.addEventListener('appinstalled', onInstalled);
    return () => {
      window.removeEventListener('beforeinstallprompt', onPrompt);
      window.removeEventListener('appinstalled', onInstalled);
    };
  }, []);

  if (promptEvent === null || hidden) return null;

  async function install() {
    if (promptEvent === null) return;
    await promptEvent.prompt();
    const choice = await promptEvent.userChoice;
    if (choice.outcome === 'dismissed') {
      rememberDismissed();
    }
    setPromptEvent(null);
  }

  function dismiss() {
    rememberDismissed();
    setHidden(true);
  }

  return (
    <div className="pwa-banner" role="dialog" aria-label={t('Instalează Companion pe telefon')}>
      <div className="pwa-banner-text">
        <b>{t('Instalează Companion pe telefon')}</b>
        <span className="muted">{t('Accesează rapid cartea de service, documentele și mesajele service-ului.')}</span>
      </div>
      <div className="pwa-banner-actions">
        <button type="button" className="btn" onClick={install}>
          {t('Instalează')}
        </button>
        <button type="button" className="btn btn-ghost" onClick={dismiss} aria-label={t('Închide')}>
          <Icon name="close" size={16} />
        </button>
      </div>
    </div>
  );
}
