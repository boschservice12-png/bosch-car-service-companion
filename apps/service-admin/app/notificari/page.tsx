'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, type UpcomingDeadline } from '@/lib/api';
import { ApiError } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { Loading, EmptyState, ErrorState } from '@/components/states';

/**
 * P1-03 (decizie de produs): notificările pleacă de pe WhatsApp-ul PROPRIU al
 * service-ului (fără API plătit) și pe email. Pagina compune mesajul; operatorul
 * apasă, WhatsApp/emailul se deschide cu textul gata scris, iar trimiterea se
 * consemnează pe scadență.
 */

const SERVICE_PHONE = '0730 508 343';

/** Număr pentru wa.me: doar cifre, prefixat cu 40 (România) când e nevoie. */
function waNumber(raw: string): string {
  let d = raw.replace(/\D/g, '');
  if (d.startsWith('0040')) d = d.slice(2);
  else if (d.startsWith('0')) d = '4' + d;
  return d;
}

/**
 * Evidența veche conține și „telefoane" de forma „N/A" sau „-" — un link
 * wa.me fără număr real nu are sens, deci butonul rămâne dezactivat.
 */
function hasUsablePhone(raw: string | null | undefined): boolean {
  return !!raw && waNumber(raw).length >= 8;
}

function reminderText(item: UpcomingDeadline): string {
  const name = item.owner?.name ? `, ${item.owner.name}` : '';
  const when =
    item.daysLeft === null ? '' : item.daysLeft < 0 ? ' (deja expirat)' : ` (în ${item.daysLeft} zile)`;
  return (
    `Bună ziua${name}! Vă reamintim de la Bosch Car Service Szkaliczki: ` +
    `${item.typeLabel} pentru ${item.vehicle.plateNumber} expiră la ${item.expiresAt}${when}. ` +
    `Vă putem ajuta cu reînnoirea sau programarea — telefon ${SERVICE_PHONE}.`
  );
}

const WINDOWS = [7, 30, 60, 90];

export default function NotificationsPage() {
  const router = useRouter();
  const t = useT();
  const [days, setDays] = useState(30);
  const [items, setItems] = useState<UpcomingDeadline[] | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setError(null);
    setItems(null);
    api
      .upcomingDeadlines(days)
      .then((res) => setItems(res.items))
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [days, router]);

  useEffect(load, [load]);

  async function markAndOpen(item: UpcomingDeadline, channel: 'whatsapp' | 'email', url: string) {
    window.open(url, channel === 'whatsapp' ? '_blank' : '_self');
    try {
      const res = await api.markDeadlineNotified(item.id, channel);
      setItems((list) =>
        list ? list.map((i) => (i.id === item.id ? { ...i, lastNotifiedAt: res.notifiedAt } : i)) : list,
      );
    } catch (err) {
      // Mesajul s-a deschis, dar consemnarea a eșuat — operatorul trebuie să
      // afle (sesiune expirată → login; altfel eroarea rămâne vizibilă).
      if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
        router.replace('/login');
        return;
      }
      setError(err instanceof ApiError ? err.problem.title : 'Consemnarea notificării a eșuat.');
    }
  }

  return (
    <>
      <header className="page-head">
        <div>
          <h1>{t('Notificări de scadență')}</h1>
        </div>
        <div className="page-head-actions">
          <Link href="/" className="btn btn-ghost btn-sm">
            {t('Înapoi')}
          </Link>
        </div>
      </header>
      <p className="muted">
        {t('Mesajul se deschide gata scris în WhatsApp-ul service-ului sau în email — îl trimiteți cu un click, iar trimiterea se consemnează.')}
      </p>
      <div className="card callout callout-whatsapp" style={{ marginBottom: 'var(--s3)' }}>
        <b><Icon name="phone" size={15} /> {t('Expeditor: WhatsApp-ul service-ului')} — <span className="tabnum">{SERVICE_PHONE}</span></b>
        <div className="muted" style={{ fontSize: 'var(--text-sm)' }}>
          {t('Folosiți acest calculator cu WhatsApp Web conectat la numărul de mai sus — mesajele pleacă de pe numărul cu care sunteți autentificați.')}
        </div>
      </div>

      <div className="field" style={{ maxWidth: 260 }}>
        <label htmlFor="window">{t('Fereastra de timp')}</label>
        <select id="window" value={days} onChange={(e) => setDays(Number(e.target.value))}>
          {WINDOWS.map((w) => (
            <option key={w} value={w}>
              {t('Următoarele {n} zile', { n: w })}
            </option>
          ))}
        </select>
      </div>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && items === null ? <Loading /> : null}
      {items?.length === 0 ? (
        <EmptyState title={t('Nicio scadență în fereastra aleasă')} />
      ) : null}

      {items && items.length > 0 ? (
        <div className="card" style={{ marginTop: 8 }}>
          {items.map((item) => {
            const expired = item.daysLeft !== null && item.daysLeft < 0;
            return (
              <div key={item.id} className="list-row" style={{ alignItems: 'flex-start' }}>
                <div>
                  <strong>
                    {item.vehicle.plateNumber} · {t(item.typeLabel)}
                  </strong>{' '}
                  <span className={`badge ${expired ? 'badge-err' : 'badge-warn'}`}>
                    {expired
                      ? t('expirat')
                      : t('în {n} zile', { n: item.daysLeft ?? 0 })}{' '}
                    · {item.expiresAt}
                  </span>
                  <div className="muted" style={{ fontSize: 'var(--text-sm)' }}>
                    {[item.vehicle.make, item.vehicle.model].filter(Boolean).join(' ')}
                    {item.owner?.name ? ` · ${item.owner.name}` : ''}
                    {item.owner?.phone ? ` · ${item.owner.phone}` : ` · ${t('fără telefon')}`}
                  </div>
                  {item.lastNotifiedAt ? (
                    <div className="muted" style={{ fontSize: 'var(--text-xs)' }}>
                      <Icon name="check" size={12} /> {t('Notificat ultima dată: {date}', { date: new Date(item.lastNotifiedAt).toLocaleString('ro-RO') })}
                    </div>
                  ) : null}
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                  <button
                    type="button"
                    className="btn btn-sm"
                    disabled={!hasUsablePhone(item.owner?.phone)}
                    onClick={() =>
                      hasUsablePhone(item.owner?.phone) &&
                      markAndOpen(
                        item,
                        'whatsapp',
                        `https://wa.me/${waNumber(item.owner!.phone!)}?text=${encodeURIComponent(reminderText(item))}`,
                      )
                    }
                  >
                    <Icon name="message" size={16} /> {t('WhatsApp')}
                  </button>
                  <button
                    type="button"
                    className="btn btn-ghost btn-sm"
                    disabled={!item.owner?.email}
                    onClick={() =>
                      item.owner?.email &&
                      markAndOpen(
                        item,
                        'email',
                        `mailto:${item.owner.email}?subject=${encodeURIComponent(
                          `${item.typeLabel} – ${item.vehicle.plateNumber} – expiră ${item.expiresAt}`,
                        )}&body=${encodeURIComponent(reminderText(item))}`,
                      )
                    }
                  >
                    <Icon name="mail" size={16} /> {t('Email')}
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      ) : null}
    </>
  );
}
