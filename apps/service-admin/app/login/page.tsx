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
  // P0-06: al doilea pas — cod TOTP sau cod de rezervă.
  const [otpStep, setOtpStep] = useState(false);
  const [otp, setOtp] = useState('');
  const [useRecovery, setUseRecovery] = useState(false);
  // După un cod de rezervă folosit: câte au mai rămas (avertisment înainte de portal).
  const [recoveryLeft, setRecoveryLeft] = useState<number | null>(null);

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
      if (res.requiresOtp) {
        setOtpStep(true);
        return;
      }
      router.replace('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
    } finally {
      setBusy(false);
    }
  }

  async function onVerifyOtp(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const res = await api.verify2fa(useRecovery ? { recoveryCode: otp } : { code: otp });
      if (typeof res.recoveryCodesLeft === 'number') {
        // Codul de rezervă e de unică folosință — adminul trebuie să știe câte
        // i-au mai rămas (și să regenereze la nevoie) înainte de a merge mai departe.
        setRecoveryLeft(res.recoveryCodesLeft);
        return;
      }
      router.replace('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'A apărut o eroare.');
    } finally {
      setBusy(false);
    }
  }

  if (recoveryLeft !== null) {
    return (
      <>
        <h1>{t('Verificare în doi pași')}</h1>
        <div className={`alert ${recoveryLeft <= 2 ? 'alert-err' : 'alert-warn'}`} role="alert">
          {t('Ați folosit un cod de rezervă. Coduri rămase: {n}.', { n: recoveryLeft })}
          {recoveryLeft <= 2
            ? ' ' + t('Reactivați 2FA din pagina Securitate pentru coduri noi.')
            : null}
        </div>
        <button className="btn" onClick={() => router.replace('/')}>
          {t('Continuă în portal')}
        </button>
      </>
    );
  }

  if (otpStep) {
    return (
      <>
        <h1>{t('Verificare în doi pași')}</h1>
        <p className="muted">
          {useRecovery
            ? t('Introduceți unul dintre codurile de rezervă primite la activarea 2FA.')
            : t('Introduceți codul din aplicația de autentificare (Google/Microsoft Authenticator).')}
        </p>
        {error ? (
          <div className="alert alert-err" role="alert">
            {t(error)}
          </div>
        ) : null}
        <form onSubmit={onVerifyOtp} noValidate>
          <div className="field">
            <label htmlFor="otp">{useRecovery ? t('Cod de rezervă') : t('Cod din aplicație (6 cifre)')}</label>
            <input
              id="otp"
              inputMode={useRecovery ? 'text' : 'numeric'}
              autoComplete="one-time-code"
              placeholder={useRecovery ? 'XXXX-XXXX' : '000000'}
              value={otp}
              onChange={(e) => setOtp(e.target.value)}
              autoFocus
              required
            />
          </div>
          <button className="btn" type="submit" disabled={busy || otp.trim() === ''}>
            {busy ? t('Se verifică…') : t('Verifică')}
          </button>
        </form>
        <p style={{ marginTop: 12 }}>
          <button
            type="button"
            className="btn btn-ghost"
            style={{ width: 'auto', padding: '6px 10px' }}
            onClick={() => {
              setUseRecovery((v) => !v);
              setOtp('');
              setError(null);
            }}
          >
            {useRecovery ? t('Folosesc codul din aplicație') : t('Nu am telefonul — folosesc un cod de rezervă')}
          </button>
        </p>
      </>
    );
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
