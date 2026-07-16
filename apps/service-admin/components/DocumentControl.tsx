'use client';

import { useRef, useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES, type Deadline } from '@/lib/types';

const ACCEPT_ATTR = Object.values(UPLOAD_ACCEPT).join(',');
const ALLOWED_MIMES = Object.keys(UPLOAD_ACCEPT);

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** Validare client-side (tip + dimensiune) înainte de upload — oglindește backend-ul. */
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

/**
 * Ataşare / descărcare document pentru o scadență (portal service). Fluxul: upload
 * (multipart) → ataşare la scadență → reîncărcare. Descărcarea folosește un URL
 * temporar semnat, cu autorizare la nivel de obiect pe server.
 */
export function DocumentControl({ deadline, onChange }: { deadline: Deadline; onChange: () => void }) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const doc = deadline.document;

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
      await api.attachDocument(deadline.id, uploaded.id);
      onChange();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Încărcare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  async function download() {
    if (!doc) return;
    setError(null);
    setBusy(true);
    try {
      const { url } = await api.documentDownloadUrl(doc.id);
      window.open(url, '_blank', 'noopener');
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Descărcare eșuată.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="stack" style={{ gap: 6 }}>
      {doc ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <span aria-hidden>📎</span>
          <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{doc.originalName ?? 'document'}</span>
          <span className="muted" style={{ fontSize: '0.78rem' }}>
            {formatSize(doc.sizeBytes)}
          </span>
          {doc.scanStatus === 'PENDING' ? (
            <span className="badge badge-unknown">
              <span aria-hidden>•</span> în curs de scanare
            </span>
          ) : null}
          {doc.scanStatus === 'INFECTED' ? (
            <span className="badge badge-err">
              <span aria-hidden>×</span> respins (malware)
            </span>
          ) : null}
          {doc.servable ? (
            <button
              className="btn btn-ghost"
              style={{ width: 'auto', padding: '6px 12px' }}
              disabled={busy}
              onClick={download}
            >
              {busy ? '…' : 'Descarcă'}
            </button>
          ) : null}
        </div>
      ) : (
        <span className="muted" style={{ fontSize: '0.85rem' }}>
          Niciun document ataşat.
        </span>
      )}

      <div>
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT_ATTR}
          onChange={onPick}
          style={{ display: 'none' }}
          aria-hidden
        />
        <button
          className="btn btn-ghost"
          style={{ width: 'auto', padding: '6px 12px' }}
          disabled={busy}
          onClick={() => inputRef.current?.click()}
        >
          {busy ? 'Se încarcă…' : doc ? 'Înlocuieşte documentul' : '📎 Ataşează document'}
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
