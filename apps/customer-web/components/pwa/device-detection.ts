/**
 * Detectare simplă de platformă pentru fluxul de instalare PWA.
 * Doar euristici de user-agent — suficiente pentru a alege ce ghid afișăm;
 * nu se folosesc la decizii de securitate.
 */

export function isIos(): boolean {
  if (typeof navigator === 'undefined') return false;
  const ua = navigator.userAgent;
  // iPadOS 13+ se anunță ca Mac, dar are touch.
  const iPadOs = ua.includes('Macintosh') && typeof document !== 'undefined' && 'ontouchend' in document;
  return /iPhone|iPad|iPod/.test(ua) || iPadOs;
}

/** Safari „adevărat" pe iOS (Chrome/Firefox pe iOS nu pot instala PWA-uri). */
export function isIosSafari(): boolean {
  if (!isIos() || typeof navigator === 'undefined') return false;
  const ua = navigator.userAgent;
  return /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo/.test(ua);
}

/** Ecran de telefon — nu afișăm panoul de instalare inutil pe desktop. */
export function isSmallScreen(): boolean {
  if (typeof window === 'undefined') return false;
  return window.matchMedia('(max-width: 768px)').matches;
}
