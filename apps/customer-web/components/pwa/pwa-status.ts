/**
 * Starea PWA: rulează deja ca aplicație instalată? A închis clientul panoul?
 * În localStorage ținem DOAR data închiderii panoului (nu e o dată personală).
 */

const DISMISS_KEY = 'bcsc.pwa.installDismissedAt';
const DISMISS_DAYS = 7;

/** Aplicația rulează din ecranul principal (standalone), nu ca filă de browser. */
export function isStandalone(): boolean {
  if (typeof window === 'undefined') return false;
  const nav = window.navigator as Navigator & { standalone?: boolean };
  return window.matchMedia('(display-mode: standalone)').matches || nav.standalone === true;
}

/** Panoul a fost închis în ultimele 7 zile? */
export function isRecentlyDismissed(): boolean {
  if (typeof window === 'undefined') return true;
  try {
    const at = window.localStorage.getItem(DISMISS_KEY);
    if (at === null) return false;
    return Date.now() - Number(at) < DISMISS_DAYS * 24 * 60 * 60 * 1000;
  } catch {
    return false;
  }
}

export function rememberDismissed(): void {
  try {
    window.localStorage.setItem(DISMISS_KEY, String(Date.now()));
  } catch {
    // localStorage indisponibil (mod privat) — panoul va reapărea, acceptabil.
  }
}
