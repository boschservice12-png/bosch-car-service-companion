'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Me } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { Loading, ErrorState } from '@/components/states';

type Step = 'status' | 'confirm' | 'recovery';

/**
 * P0-06 — securitatea contului de service: activarea / dezactivarea
 * autentificării în doi pași (TOTP). Codurile de rezervă se afișează
 * O SINGURĂ DATĂ, imediat după activare.
 */
export default function SecurityPage() {
  const router = useRouter();
  const t = useT();
  const [me, setMe] = useState<Me | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const [step, setStep] = useState<Step>('status');
  const [password, setPassword] = useState('');
  const [secret, setSecret] = useState('');
  const [otpauthUri, setOtpauthUri] = useState('');
  const [code, setCode] = useState('');
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
  const [disableCode, setDisableCode] = useState('');

  const load = useCallback(() => {
    setError(null);
    setMe(null);
    api
      .me()
      .then(setMe)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Nu am putut încărca datele contului.');
      });
  }, [router]);

  useEffect(load, [load]);

  async function onSetup(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setBusy(true);
    try {
      const res = await api.setup2fa(password);
      setSecret(res.secret);
      setOtpauthUri(res.otpauthUri);
      setPassword('');
      setStep('confirm');
    } catch (err) {
      setFormError(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
    } finally {
      setBusy(false);
    }
  }

  async function onEnable(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setBusy(true);
    try {
      const res = await api.enable2fa(code);
      setRecoveryCodes(res.recoveryCodes);
      setCode('');
      setStep('recovery');
    } catch (err) {
      setFormError(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
    } finally {
      setBusy(false);
    }
  }

  async function onDisable(e: React.FormEvent) {
    e.preventDefault();
    setFormError(null);
    setBusy(true);
    try {
      await api.disable2fa(disableCode);
      setDisableCode('');
      load();
    } catch (err) {
      setFormError(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <header className="page-head">
        <div>
          <h1>{t('Securitatea contului')}</h1>
        </div>
        <div className="page-head-actions">
          <Link href="/" className="btn btn-ghost btn-sm">
            {t('Înapoi')}
          </Link>
        </div>
      </header>

      {error ? <ErrorState message={t(error)} onRetry={load} /> : null}
      {!error && me === null ? <Loading /> : null}

      {me !== null && step === 'status' ? (
        <section className="panel panel-form" style={{ marginTop: 'var(--s4)' }}>
          <div className="panel-head">
            <span className="panel-title">{t('Autentificare în doi pași (2FA)')}</span>
          </div>
          <div className="panel-body stack">
          {me.totpEnabled ? (
            <>
              <p>
                <Icon name="shield" size={16} /> {t('2FA este ACTIV pe acest cont — la fiecare login se cere codul din aplicația de autentificare.')}
              </p>
              {formError ? (
                <div className="alert alert-err" role="alert">
                  {t(formError)}
                </div>
              ) : null}
              <form onSubmit={onDisable} noValidate>
                <div className="field">
                  <label htmlFor="disable-code">{t('Cod din aplicație (6 cifre)')}</label>
                  <input
                    id="disable-code"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    placeholder="000000"
                    value={disableCode}
                    onChange={(e) => setDisableCode(e.target.value)}
                    required
                  />
                </div>
                <button className="btn btn-ghost" type="submit" disabled={busy || disableCode.trim() === ''}>
                  {busy ? t('Se dezactivează…') : t('Dezactivează 2FA')}
                </button>
              </form>
            </>
          ) : (
            <>
              <p className="muted">
                {t('Protejați contul de administrator cu un al doilea factor: un cod generat de aplicația de autentificare (Google/Microsoft Authenticator, Authy).')}
              </p>
              {formError ? (
                <div className="alert alert-err" role="alert">
                  {t(formError)}
                </div>
              ) : null}
              <form onSubmit={onSetup} noValidate>
                <div className="field">
                  <label htmlFor="password">{t('Confirmați parola contului')}</label>
                  <input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    required
                  />
                </div>
                <button className="btn" type="submit" disabled={busy || password === ''}>
                  {busy ? t('Se pregătește…') : t('Activează 2FA')}
                </button>
              </form>
            </>
          )}
          </div>
        </section>
      ) : null}

      {step === 'confirm' ? (
        <section className="panel panel-form" style={{ marginTop: 'var(--s4)' }}>
          <div className="panel-head">
            <span className="panel-title">{t('Pasul 2: adăugați contul în aplicația de autentificare')}</span>
          </div>
          <div className="panel-body stack">
          <p className="muted">
            {t('Scanați codul QR din aplicație folosind linkul de mai jos sau introduceți secretul manual, apoi confirmați cu primul cod generat.')}
          </p>
          <div className="field">
            <label>{t('Secret (introducere manuală)')}</label>
            <code style={{ display: 'block', padding: 8, fontSize: 16, letterSpacing: 1, userSelect: 'all' }}>{secret}</code>
          </div>
          <div className="field">
            <label>{t('Link de configurare (otpauth)')}</label>
            <code style={{ display: 'block', padding: 8, fontSize: 12, wordBreak: 'break-all', userSelect: 'all' }}>{otpauthUri}</code>
          </div>
          {formError ? (
            <div className="alert alert-err" role="alert">
              {t(formError)}
            </div>
          ) : null}
          <form onSubmit={onEnable} noValidate>
            <div className="field">
              <label htmlFor="code">{t('Cod din aplicație (6 cifre)')}</label>
              <input
                id="code"
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="000000"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                autoFocus
                required
              />
            </div>
            <button className="btn" type="submit" disabled={busy || code.trim() === ''}>
              {busy ? t('Se confirmă…') : t('Confirmă și activează')}
            </button>
          </form>
          </div>
        </section>
      ) : null}

      {step === 'recovery' ? (
        <section className="panel panel-form" style={{ marginTop: 'var(--s4)' }}>
          <div className="panel-head">
            <span className="panel-title">{t('2FA activat')}</span>
          </div>
          <div className="panel-body stack">
          <div className="alert alert-warn" role="alert">
            {t('Salvați codurile de rezervă de mai jos într-un loc sigur — se afișează O SINGURĂ DATĂ. Fiecare cod funcționează o singură dată, dacă pierdeți accesul la aplicația de autentificare.')}
          </div>
          <ul style={{ columns: 2, listStyle: 'none', padding: 8, fontFamily: 'monospace', fontSize: 16 }}>
            {recoveryCodes.map((c) => (
              <li key={c} style={{ padding: 4, userSelect: 'all' }}>
                {c}
              </li>
            ))}
          </ul>
          <button
            className="btn"
            type="button"
            onClick={() => {
              setRecoveryCodes([]);
              setStep('status');
              load();
            }}
          >
            {t('Am salvat codurile de rezervă')}
          </button>
          </div>
        </section>
      ) : null}
    </>
  );
}
