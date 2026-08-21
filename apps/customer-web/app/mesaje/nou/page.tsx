'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { ApiError, type Vehicle } from '@/lib/types';
import { useT } from '@/lib/i18n';

export default function NewConversationPage() {
  const router = useRouter();
  const t = useT();
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [vehicleId, setVehicleId] = useState('');
  const [vehicles, setVehicles] = useState<Vehicle[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.vehicles().then(setVehicles).catch(() => setVehicles([]));
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      const conv = await api.startConversation({
        subject,
        body,
        vehicleId: vehicleId || undefined,
      });
      router.replace(`/mesaje/${conv.id}`);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
      setBusy(false);
    }
  }

  return (
    <>
      <header className="page-head">
        <div>
  <Link href="/mesaje" className="back-link">
          <Icon name="arrow-left" size={14} />
          {t('Mesaje')}
        </Link>
          <h1>{t('Mesaj nou')}</h1>
        </div>
      </header>
      {error ? (
        <div className="alert alert-err" role="alert">
          {t(error)}
        </div>
      ) : null}
      <form onSubmit={submit} className="panel panel-body panel-form stack">
        <div className="field">
          <label htmlFor="subject">{t('Subiect')}</label>
          <input id="subject" type="text" maxLength={200} value={subject} onChange={(e) => setSubject(e.target.value)} required />
        </div>
        <div className="field">
          <label htmlFor="vehicle">{t('Vehicul (opțional)')}</label>
          <select id="vehicle" value={vehicleId} onChange={(e) => setVehicleId(e.target.value)}>
            <option value="">{t('— fără vehicul —')}</option>
            {vehicles.map((v) => (
              <option key={v.id} value={v.id}>
                {v.plateNumber}
              </option>
            ))}
          </select>
        </div>
        <div className="field">
          <label htmlFor="body">{t('Mesaj')}</label>
          <textarea id="body" rows={4} value={body} onChange={(e) => setBody(e.target.value)} required />
        </div>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? t('Se trimite…') : t('Trimite')}
        </button>
      </form>
    </>
  );
}
