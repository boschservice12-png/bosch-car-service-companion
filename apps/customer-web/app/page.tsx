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

      <div className="card" style={{ marginTop: 20 }}>
        <strong>Scadențe și solicitări</strong>
        <p className="muted">
          ITP, RCA, rovinietă, asistență rutieră, istoric service, oferte, mobilitate, dosar de daună și taxe —
          disponibile pe măsură ce sunt livrate modulele.
        </p>
      </div>

      <BottomNav />
    </>
  );
}
