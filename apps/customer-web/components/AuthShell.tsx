import Link from 'next/link';
import { Logo } from '@/components/Logo';
import { Icon, type IconName } from '@/components/Icon';

/**
 * The frame shared by every screen you can reach while signed out.
 *
 * Sign-in is the only screen where the visitor has no data of their own, so the
 * left plane states what the product actually tracks rather than showing a
 * decorative illustration. It reuses the console's dark chrome so the app does
 * not appear to change identity the moment you authenticate.
 *
 * Content is passed in rather than branched on inside, because the two callers
 * (sign in, create account) differ only in their form.
 */

const TRACKS: { icon: IconName; label: string }[] = [
  { icon: 'clipboard', label: 'Inspecție tehnică (ITP)' },
  { icon: 'shield', label: 'Asigurare (RCA)' },
  { icon: 'receipt', label: 'Rovinietă și taxe' },
  { icon: 'history', label: 'Istoric service și documente' },
];

export function AuthShell({
  lede,
  children,
  t,
}: {
  /** One line naming what the product does, in the visitor's language. */
  lede: string;
  children: React.ReactNode;
  t: (s: string) => string;
}) {
  return (
    <div className="auth">
      <aside className="auth-brand">
        <Link href="/" className="auth-brand-lockup">
          <Logo size={40} className="auth-brand-mark" />
          <span>
            <span className="auth-brand-name">Companion</span>
            <br />
            <span className="auth-brand-sub">Bosch Car Service</span>
          </span>
        </Link>

        <div className="auth-brand-body">
          <p className="auth-lede">{lede}</p>

          <ul className="auth-tracks">
            {TRACKS.map((track) => (
              <li key={track.label} className="auth-track">
                <Icon name={track.icon} size={17} />
                {t(track.label)}
              </li>
            ))}
          </ul>
        </div>

        <p className="auth-foot">SC Szkaliczki Service SRL</p>
      </aside>

      <main className="auth-panel">
        <div className="auth-form">{children}</div>
      </main>
    </div>
  );
}
