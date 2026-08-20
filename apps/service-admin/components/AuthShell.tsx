import { Logo } from '@/components/Logo';
import { Icon, type IconName } from '@/components/Icon';

/**
 * The frame for the portal's signed-out screens.
 *
 * Same construction as the customer app's sign-in — dark chrome plane beside
 * the work surface — so the two products read as one family. What differs is
 * the content: staff already know what the portal is, so the left plane states
 * what they can act on rather than selling anything, and it says plainly that
 * two-factor is required, because that is the single thing most likely to
 * block someone at this screen.
 */

const CAPABILITIES: { icon: IconName; label: string }[] = [
  { icon: 'car', label: 'Vehicule și scadențe' },
  { icon: 'wrench', label: 'Cereri de ofertă' },
  { icon: 'sos', label: 'Asistență rutieră și daune' },
  { icon: 'history', label: 'Carte de service' },
];

export function AuthShell({
  lede,
  children,
  t,
}: {
  lede: string;
  children: React.ReactNode;
  t: (s: string) => string;
}) {
  return (
    <div className="auth">
      <aside className="auth-brand">
        <span className="auth-brand-lockup">
          <Logo size={40} className="auth-brand-mark" />
          <span>
            <span className="auth-brand-name">Portal Service</span>
            <br />
            <span className="auth-brand-sub">Bosch Car Service</span>
          </span>
        </span>

        <div className="auth-brand-body">
          <p className="auth-lede">{lede}</p>

          <ul className="auth-tracks">
            {CAPABILITIES.map((c) => (
              <li key={c.label} className="auth-track">
                <Icon name={c.icon} size={17} />
                {t(c.label)}
              </li>
            ))}
          </ul>
        </div>

        <p className="auth-foot">
          <Icon name="shield" size={14} /> {t('Accesul cere autentificare în doi pași.')}
        </p>
      </aside>

      <main className="auth-panel">
        <div className="auth-form">{children}</div>
      </main>
    </div>
  );
}
