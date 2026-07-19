'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError } from '@/lib/types';
import { useT } from '@/lib/i18n';

export default function AdminLoginPage() {
  const router = useRouter();
  const t = useT();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await api.login(email, password);
      if (res.role !== 'SERVICE_ADMIN') {
        setError('Acest portal este doar pentru administratorii service-ului.');
        await api.logout().catch(() => undefined);
        return;
      }
      router.replace('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <h1>{t('Portal Service')}</h1>
      <p className="muted">{t('Autentificare administrator.')}</p>
      {error ? (
        <div className="alert alert-err" role="alert">
          {t(error)}
        </div>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <div className="field">
          <label htmlFor="email">{t('Email')}</label>
          <input id="email" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
        </div>
        <div className="field">
          <label htmlFor="password">{t('Parolă')}</label>
          <input
            id="password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? t('Se conectează…') : t('Intră în portal')}
        </button>
      </form>
    </>
  );
}
