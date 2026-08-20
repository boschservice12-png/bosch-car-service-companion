'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { AuthShell } from '@/components/AuthShell';

export default function RegisterPage() {
  const router = useRouter();
  const t = useT();
  const [form, setForm] = useState({
    email: '',
    password: '',
    firstName: '',
    lastName: '',
    consent: false,
  });
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [general, setGeneral] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  function set<K extends keyof typeof form>(key: K, value: (typeof form)[K]) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault();
    setErrors({});
    setGeneral(null);
    setBusy(true);
    try {
      await api.register(form);
      // După înregistrare, autentificare automată.
      await api.login(form.email, form.password);
      router.replace('/');
    } catch (err) {
      if (err instanceof ApiError && err.problem.errors) {
        setErrors(err.problem.errors);
      } else {
        setGeneral(err instanceof ApiError ? err.problem.title : t('A apărut o eroare.'));
      }
    } finally {
      setBusy(false);
    }
  }

  const fieldErr = (name: string) => errors[name]?.[0];

  return (
    <AuthShell t={t} lede={t('Scadențele mașinii, într-un singur loc.')}>
      <p className="eyebrow">{t('Cont client')}</p>
      <h1>{t('Creare cont')}</h1>
      <p className="auth-intro">{t('Vă luați câteva minute o dată; după aceea scadențele vin la dumneavoastră.')}</p>
      {general ? (
        <div className="alert alert-err" role="alert">
          {t(general)}
        </div>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <div className="field">
          <label htmlFor="firstName">{t('Prenume')}</label>
          <input id="firstName" value={form.firstName} onChange={(e) => set('firstName', e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="lastName">{t('Nume')}</label>
          <input id="lastName" value={form.lastName} onChange={(e) => set('lastName', e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="email">{t('Email')}</label>
          <input id="email" type="email" autoComplete="email" value={form.email} onChange={(e) => set('email', e.target.value)} required />
          {fieldErr('email') ? <div className="err">{t(fieldErr('email') as string)}</div> : null}
        </div>
        <div className="field">
          <label htmlFor="password">{t('Parolă')}</label>
          <input
            id="password"
            type="password"
            autoComplete="new-password"
            value={form.password}
            onChange={(e) => set('password', e.target.value)}
            required
          />
          <div className="hint">{t('Minim 8 caractere.')}</div>
          {fieldErr('password') ? <div className="err">{t(fieldErr('password') as string)}</div> : null}
        </div>
        <div className="hint" style={{ marginBottom: 12 }}>
          {t('Dacă sunteți deja client al service-ului, cereți service-ului un cod de activare pentru vehicul și introduceți-l după autentificare (pagina Vehicule).')}
        </div>
        <div className="field">
          <label className="check">
            <input type="checkbox" checked={form.consent} onChange={(e) => set('consent', e.target.checked)} />
            <span>{t('Sunt de acord cu prelucrarea datelor mele personale conform informării de confidențialitate.')}</span>
          </label>
          {fieldErr('consent') ? <div className="err">{t(fieldErr('consent') as string)}</div> : null}
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? t('Se creează…') : t('Creează cont')}
        </button>
      </form>
      <p className="auth-alt">
        {t('Aveți deja cont?')} <Link href="/login">{t('Autentificați-vă')}</Link>
      </p>
    </AuthShell>
  );
}
