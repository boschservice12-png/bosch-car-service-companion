'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import type { Me } from '@/lib/types';
import { BottomNav } from '@/components/BottomNav';
import { Loading } from '@/components/states';

export default function ProfilePage() {
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

  async function logout() {
    await api.logout().catch(() => undefined);
    router.replace('/login');
  }

  if (loading) return <Loading rows={2} />;
  if (!me) return null;

  return (
    <>
      <h1>Profil</h1>
      <div className="card stack">
        {me.name ? (
          <div>
            <span className="muted">Nume:</span> {me.name}
          </div>
        ) : null}
        <div>
          <span className="muted">Email:</span> {me.email}
        </div>
        <div>
          <span className="muted">Rol:</span> {me.role === 'SERVICE_ADMIN' ? 'Administrator service' : 'Client'}
        </div>
      </div>

      <button className="btn btn-ghost" onClick={logout}>
        Deconectare
      </button>

      <BottomNav />
    </>
  );
}
