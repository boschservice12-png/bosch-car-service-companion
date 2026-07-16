'use client';

import Link from 'next/link';
import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError } from '@/lib/types';

export default function RegisterPage() {
  const router = useRouter();
  const [form, setForm] = useState({ email: '', password: '', firstName: '', lastName: '', consent: false });
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
        setGeneral(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
      }
    } finally {
      setBusy(false);
    }
  }

  const fieldErr = (name: string) => errors[name]?.[0];

  return (
    <>
      <h1>Creare cont</h1>
      {general ? (
        <div className="alert alert-err" role="alert">
          {general}
        </div>
      ) : null}
      <form onSubmit={onSubmit} noValidate>
        <div className="field">
          <label htmlFor="firstName">Prenume</label>
          <input id="firstName" value={form.firstName} onChange={(e) => set('firstName', e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="lastName">Nume</label>
          <input id="lastName" value={form.lastName} onChange={(e) => set('lastName', e.target.value)} />
        </div>
        <div className="field">
          <label htmlFor="email">Email</label>
          <input id="email" type="email" autoComplete="email" value={form.email} onChange={(e) => set('email', e.target.value)} required />
          {fieldErr('email') ? <div className="err">{fieldErr('email')}</div> : null}
        </div>
        <div className="field">
          <label htmlFor="password">Parolă</label>
          <input
            id="password"
            type="password"
            autoComplete="new-password"
            value={form.password}
            onChange={(e) => set('password', e.target.value)}
            required
          />
          <div className="hint">Minim 8 caractere.</div>
          {fieldErr('password') ? <div className="err">{fieldErr('password')}</div> : null}
        </div>
        <div className="field">
          <label style={{ display: 'flex', gap: 8, alignItems: 'flex-start', fontWeight: 400 }}>
            <input type="checkbox" checked={form.consent} onChange={(e) => set('consent', e.target.checked)} />
            <span>Sunt de acord cu prelucrarea datelor mele personale conform informării de confidențialitate.</span>
          </label>
          {fieldErr('consent') ? <div className="err">{fieldErr('consent')}</div> : null}
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se creează…' : 'Creează cont'}
        </button>
      </form>
      <p className="center muted" style={{ marginTop: 16 }}>
        Aveți deja cont? <Link href="/login">Autentificați-vă</Link>
      </p>
    </>
  );
}
