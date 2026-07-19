'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError } from '@/lib/types';
import { useT } from '@/lib/i18n';

export default function LoginPage() {
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
      await api.login(email, password);
      router.replace('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : t('A apărut o eroare. Încercați din nou.'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <h1>{t('Autentificare')}</h1>
      {error ? (
        <div className="alert alert-err" role="alert">
          {error}
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
          {busy ? t('Se conectează…') : t('Intră în cont')}
        </button>
      </form>
      <p className="center muted" style={{ marginTop: 16 }}>
        {t('Nu aveți cont?')} <Link href="/register">{t('Creați unul')}</Link>
      </p>
    </>
  );
}
