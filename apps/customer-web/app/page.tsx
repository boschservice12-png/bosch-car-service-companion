'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import type { Me } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading } from '@/components/states';

export default function HomePage() {
  const router = useRouter();
  const t = useT();
  const [me, setMe] = useState<Me | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .me()
      .then(setMe)
      .catch(() => router.replace('/login'))
      .finally(() => setLoading(false));
  }, [router]);

  if (loading) return <Loading />;
  if (!me) return null;

  return (
    <>
      <h1>
        {t('Bună,')} {me.name ?? t('client')} 👋
      </h1>
      <p className="muted">{t('Bine ați venit la Bosch Car Service Companion.')}</p>

      <h2>{t('Scurtături')}</h2>
      <div className="stack">
        <Link className="btn" href="/vehicule">
          {t('🚗 Vehiculele mele')}
        </Link>
        <Link className="btn btn-ghost" href="/vehicule/nou">
          {t('➕ Adaugă un vehicul')}
        </Link>
      </div>

      <h2>{t('Servicii')}</h2>
      <div className="stack">
        <Link className="btn btn-ghost" href="/alerte">
          {t('🔔 Alerte (ITP · RCA · taxă de drum · asistență)')}
        </Link>
        <Link className="btn btn-ghost" href="/oferte">
          {t('🧰 Cere ofertă')}
        </Link>
        <Link className="btn btn-ghost" href="/mesaje">
          {t('💬 Mesaje')}
        </Link>
        <Link className="btn btn-ghost" href="/asistenta">
          {t('🆘 Asistență rutieră')}
        </Link>
        <Link className="btn btn-ghost" href="/mobilitate">
          {t('🚕 Mobilitate')}
        </Link>
        <a
          className="btn btn-ghost"
          href="https://amiabila.com/"
          target="_blank"
          rel="noopener noreferrer"
          style={{ textAlign: 'center', textDecoration: 'none' }}
        >
          {t('📋 În caz de accident ↗')}
        </a>
        <Link className="btn btn-ghost" href="/taxe">
          {t('🧾 Taxe și impozite')}
        </Link>
      </div>

      <BottomNav />
    </>
  );
}
