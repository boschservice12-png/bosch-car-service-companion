'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import type { Me } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading } from '@/components/states';

export default function HomePage() {
  const router = useRouter();
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
      <h1>Bună, {me.name ?? 'client'} 👋</h1>
      <p className="muted">Bine ați venit la Bosch Car Service Companion.</p>

      <h2>Scurtături</h2>
      <div className="stack">
        <Link className="btn" href="/vehicule">
          🚗 Vehiculele mele
        </Link>
        <Link className="btn btn-ghost" href="/vehicule/nou">
          ➕ Adaugă un vehicul
        </Link>
      </div>

      <h2>Servicii</h2>
      <div className="stack">
        <Link className="btn btn-ghost" href="/mesaje">
          💬 Mesaje și oferte
        </Link>
        <Link className="btn btn-ghost" href="/asistenta">
          🆘 Asistență rutieră
        </Link>
      </div>

      <BottomNav />
    </>
  );
}
