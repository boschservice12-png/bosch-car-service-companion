'use client';

import { useEffect, useState } from 'react';
import { Icon } from '@/components/Icon';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Me } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { BottomNav } from '@/components/BottomNav';
import { Loading } from '@/components/states';

export default function ProfilePage() {
  const router = useRouter();
  const t = useT();
  const [me, setMe] = useState<Me | null>(null);
  const [loading, setLoading] = useState(true);
  // P1-06: ștergerea contului (cu parolă + confirmare explicită).
  const [showDelete, setShowDelete] = useState(false);
  const [deletePassword, setDeletePassword] = useState('');
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

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

  // Descărcarea e o navigare directă (nu XHR) — dacă sesiunea a expirat,
  // browserul ar salva răspunsul de eroare ca fișier. Verificăm întâi sesiunea.
  async function downloadExport() {
    try {
      await api.me();
      window.location.assign('/api/me/export');
    } catch {
      router.replace('/login');
    }
  }

  if (loading) return <Loading rows={2} />;
  if (!me) return null;

  return (
    <>
      <header className="page-head">
        <div>
          <h1>{t('Profil')}</h1>
        </div>
        <div className="page-head-actions">
          <button className="btn btn-ghost btn-sm" onClick={logout}>
            {t('Deconectare')}
          </button>
        </div>
      </header>

      <section className="panel panel-form" style={{ marginBottom: 'var(--s4)' }}>
        <div className="panel-head">
          <span className="panel-title">{t('Cont')}</span>
        </div>
        <div className="panel-body">
          <dl className="dl">
            {me.name ? (
              <>
                <dt>{t('Nume:')}</dt>
                <dd>{me.name}</dd>
              </>
            ) : null}
            <dt>{t('Email:')}</dt>
            <dd>{me.email}</dd>
            <dt>{t('Rol:')}</dt>
            <dd>{me.role === 'SERVICE_ADMIN' ? t('Administrator service') : t('Client')}</dd>
          </dl>
        </div>
      </section>

      {me.role === 'CLIENT' ? (
        <section className="panel panel-form">
          <div className="panel-head">
            <span className="panel-title">{t('Datele mele (GDPR)')}</span>
          </div>
          <div className="panel-body stack">
          <button className="btn btn-ghost" onClick={downloadExport}>
            <Icon name="download" size={16} /> {t('Descarcă datele mele (JSON)')}
          </button>

          {!showDelete ? (
            <button className="btn btn-ghost btn-danger" onClick={() => setShowDelete(true)}>
              <Icon name="trash" size={16} /> {t('Șterge contul…')}
            </button>
          ) : (
            <form
              onSubmit={async (e) => {
                e.preventDefault();
                setDeleteError(null);
                setBusy(true);
                try {
                  await api.requestAccountDeletion(deletePassword);
                  router.replace('/login');
                } catch (err) {
                  setDeleteError(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
                } finally {
                  setBusy(false);
                }
              }}
              noValidate
            >
              <p className="muted" style={{ fontSize: 'var(--text-sm)' }}>
                {t('Contul se blochează imediat, iar după 30 de zile datele personale se șterg definitiv. În acest interval vă puteți răzgândi contactând service-ul.')}
              </p>
              {deleteError ? (
                <div className="alert alert-err" role="alert">
                  {t(deleteError)}
                </div>
              ) : null}
              <div className="field">
                <label htmlFor="delete-password">{t('Confirmați parola')}</label>
                <input
                  id="delete-password"
                  type="password"
                  autoComplete="current-password"
                  value={deletePassword}
                  onChange={(e) => setDeletePassword(e.target.value)}
                  required
                />
              </div>
              <div style={{ display: 'flex', gap: 'var(--s2)' }}>
                <button className="btn btn-danger" type="submit" disabled={busy || deletePassword === ''}>
                  {busy ? t('Se trimite…') : t('Confirm ștergerea contului')}
                </button>
                <button className="btn btn-ghost" type="button" onClick={() => setShowDelete(false)}>
                  {t('Renunță')}
                </button>
              </div>
            </form>
          )}
          </div>
        </section>
      ) : null}

      <BottomNav />
    </>
  );
}
