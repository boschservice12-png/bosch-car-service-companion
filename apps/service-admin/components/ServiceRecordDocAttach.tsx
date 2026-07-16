'use client';

import { useRef, useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES } from '@/lib/types';

const ACCEPT_ATTR = Object.values(UPLOAD_ACCEPT).join(',');
const ALLOWED_MIMES = Object.keys(UPLOAD_ACCEPT);

function validate(file: File): string | null {
  if (file.size <= 0) return 'Fișier gol.';
  if (file.size > UPLOAD_MAX_BYTES) {
    return `Fișierul depășește limita de ${Math.round(UPLOAD_MAX_BYTES / (1024 * 1024))} MB.`;
  }
  if (file.type !== '' && !ALLOWED_MIMES.includes(file.type)) {
    return 'Tip de fișier nepermis. Acceptăm imagini (JPG, PNG, WEBP) și PDF.';
  }
  return null;
}

/** Încarcă un document/foto și îl atașează la o ciornă de service. */
export function ServiceRecordDocAttach({ recordId, onChange }: { recordId: string; onChange: () => void }) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onPick(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;

    const problem = validate(file);
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
      setError(err instanceof ApiError ? err.problem.title : 'Încărcare eșuată.');
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
          {busy ? 'Se încarcă…' : '📎 Atașează document / foto'}
        </button>
        <span className="muted" style={{ fontSize: '0.75rem', marginLeft: 8 }}>
          JPG, PNG, WEBP sau PDF · max {Math.round(UPLOAD_MAX_BYTES / (1024 * 1024))} MB
        </span>
      </div>
      {error ? (
        <div className="alert alert-err" role="alert" style={{ fontSize: '0.85rem' }}>
          {error}
        </div>
      ) : null}
    </div>
  );
}
