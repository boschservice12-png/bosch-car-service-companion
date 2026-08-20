'use client';

import { useRef, useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES } from '@/lib/types';
import { useT } from '@/lib/i18n';

const ACCEPT_ATTR = Object.values(UPLOAD_ACCEPT).join(',');
const ALLOWED_MIMES = Object.keys(UPLOAD_ACCEPT);

type Translator = (s: string, vars?: Record<string, string | number>) => string;

function validate(t: Translator, file: File): string | null {
  if (file.size <= 0) return t('Fișier gol.');
  if (file.size > UPLOAD_MAX_BYTES) {
    return t('Fișierul depășește limita de {n} MB.', { n: Math.round(UPLOAD_MAX_BYTES / (1024 * 1024)) });
  }
  if (file.type !== '' && !ALLOWED_MIMES.includes(file.type)) {
    return t('Tip de fișier nepermis. Acceptăm imagini (JPG, PNG, WEBP) și PDF.');
  }
  return null;
}

/** Încarcă un document/foto și îl atașează la o ciornă de service. */
export function ServiceRecordDocAttach({ recordId, onChange }: { recordId: string; onChange: () => void }) {
  const t = useT();
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onPick(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    const problem = validate(t, file);
    if (problem) {
      setError(problem);
      return;
    }

    setError(null);
    setBusy(true);
    try {
      const uploaded = await api.uploadDocument(file);
      await api.attachServiceRecordDocument(recordId, uploaded.id);
      onChange();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : t('Încărcare eșuată.'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="stack" style={{ gap: 4 }}>
      <div>
        <input ref={inputRef} type="file" accept={ACCEPT_ATTR} onChange={onPick} style={{ display: 'none' }} aria-hidden />
        <button
          className="btn btn-ghost"
          style={{ width: 'auto', padding: '6px 12px' }}
          disabled={busy}
          onClick={() => inputRef.current?.click()}
        >
          {busy ? t('Se încarcă…') : t('Atașează document / foto')}
        </button>
        <span className="muted" style={{ fontSize: 'var(--text-xs)', marginLeft: 8 }}>
          {t('JPG, PNG, WEBP sau PDF · max {n} MB', { n: Math.round(UPLOAD_MAX_BYTES / (1024 * 1024)) })}
        </span>
      </div>
      {error ? (
        <div className="alert alert-err" role="alert" style={{ fontSize: 'var(--text-sm)' }}>
          {error}
        </div>
      ) : null}
    </div>
  );
}
