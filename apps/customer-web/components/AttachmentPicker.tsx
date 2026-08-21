'use client';

import { useRef, useState } from 'react';
import { Icon } from '@/components/Icon';
import { api } from '@/lib/api';
import { ApiError, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES } from '@/lib/types';
import { useT } from '@/lib/i18n';

const ACCEPT_ATTR = Object.values(UPLOAD_ACCEPT).join(',');
const ALLOWED_MIMES = Object.keys(UPLOAD_ACCEPT);

export interface PickedFile {
  id: string;
  name: string;
}

type Translator = (s: string, vars?: Record<string, string | number>) => string;

function validate(t: Translator, file: File): string | null {
  if (file.size <= 0) return t('Fișier gol.');
  if (file.size > UPLOAD_MAX_BYTES) {
    return t('Fișierul depășește {n} MB.', { n: Math.round(UPLOAD_MAX_BYTES / (1024 * 1024)) });
  }
  if (file.type !== '' && !ALLOWED_MIMES.includes(file.type)) return t('Tip de fișier nepermis (JPG, PNG, WEBP, PDF).');
  return null;
}

/**
 * Selectează și încarcă atașamente (documente/foto). Fiecare fișier e încărcat
 * imediat, iar id-urile rezultate sunt raportate părintelui pentru a fi atașate mesajului.
 */
export function AttachmentPicker({ files, onChange }: { files: PickedFile[]; onChange: (files: PickedFile[]) => void }) {
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
      onChange([...files, { id: uploaded.id, name: file.name }]);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : t('Încărcare eșuată.'));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="stack" style={{ gap: 6 }}>
      {files.length > 0 ? (
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
          {files.map((f) => (
            <span key={f.id} className="badge badge-unknown" style={{ gap: 6 }}>
              <Icon name="paperclip" size={13} /> {f.name}
              <button
                type="button"
                aria-label={t('Elimină {name}', { name: f.name })}
                onClick={() => onChange(files.filter((x) => x.id !== f.id))}
                style={{ border: 'none', background: 'transparent', cursor: 'pointer', fontSize: 'var(--text-md)', lineHeight: 1 }}
              >
                ×
              </button>
            </span>
          ))}
        </div>
      ) : null}
      <div>
        <input ref={inputRef} type="file" accept={ACCEPT_ATTR} onChange={onPick} style={{ display: 'none' }} aria-hidden />
        <button
          type="button"
          className="btn btn-ghost"
          style={{ width: 'auto', padding: '6px 12px' }}
          disabled={busy}
          onClick={() => inputRef.current?.click()}
        >
          {busy ? t('Se încarcă…') : t('Adaugă atașament')}
        </button>
      </div>
      {error ? (
        <div className="alert alert-err" role="alert" style={{ fontSize: 'var(--text-sm)' }}>
          {error}
        </div>
      ) : null}
    </div>
  );
}
