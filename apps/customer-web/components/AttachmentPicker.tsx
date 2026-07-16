'use client';

import { useRef, useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES } from '@/lib/types';

const ACCEPT_ATTR = Object.values(UPLOAD_ACCEPT).join(',');
const ALLOWED_MIMES = Object.keys(UPLOAD_ACCEPT);

export interface PickedFile {
  id: string;
  name: string;
}

function validate(file: File): string | null {
  if (file.size <= 0) return 'Fișier gol.';
  if (file.size > UPLOAD_MAX_BYTES) return `Fișierul depășește ${Math.round(UPLOAD_MAX_BYTES / (1024 * 1024))} MB.`;
  if (file.type !== '' && !ALLOWED_MIMES.includes(file.type)) return 'Tip de fișier nepermis (JPG, PNG, WEBP, PDF).';
  return null;
}

/**
 * Selectează și încarcă atașamente (documente/foto). Fiecare fișier e încărcat
 * imediat, iar id-urile rezultate sunt raportate părintelui pentru a fi atașate mesajului.
 */
export function AttachmentPicker({ files, onChange }: { files: PickedFile[]; onChange: (files: PickedFile[]) => void }) {
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
      onChange([...files, { id: uploaded.id, name: file.name }]);
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Încărcare eșuată.');
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
              📎 {f.name}
              <button
                type="button"
                aria-label={`Elimină ${f.name}`}
                onClick={() => onChange(files.filter((x) => x.id !== f.id))}
                style={{ border: 'none', background: 'transparent', cursor: 'pointer', fontSize: '1rem', lineHeight: 1 }}
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
          {busy ? 'Se încarcă…' : '📎 Adaugă atașament'}
        </button>
      </div>
      {error ? (
        <div className="alert alert-err" role="alert" style={{ fontSize: '0.85rem' }}>
          {error}
        </div>
      ) : null}
    </div>
  );
}
