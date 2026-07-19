'use client';

import { useRef, useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, UPLOAD_ACCEPT, UPLOAD_MAX_BYTES, type Deadline } from '@/lib/types';
import { useT } from '@/lib/i18n';

const ACCEPT_ATTR = Object.values(UPLOAD_ACCEPT).join(',');
const ALLOWED_MIMES = Object.keys(UPLOAD_ACCEPT);

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

type Translator = (s: string, vars?: Record<string, string | number>) => string;

/** Validare client-side (tip + dimensiune) înainte de upload — oglindește backend-ul. */
function validate(t: Translator, file: File): string | null {
  if (file.size <= 0) return t('Fișier gol.');
  if (file.size > UPLOAD_MAX_BYTES) {
    return t('Fișierul depășește limita de {n} MB.', { n: Math.round(UPLOAD_MAX_BYTES / (1024 * 1024)) });
  }
  // Tipul e verificat și pe server (din conținut real); aici filtrăm evident greșitele.
  if (file.type !== '' && !ALLOWED_MIMES.includes(file.type)) {
    return t('Tip de fișier nepermis. Acceptăm imagini (JPG, PNG, WEBP) și PDF.');
  }
  return null;
}

/**
 * Ataşare / descărcare document pentru o scadență. Fluxul: upload (multipart) →
 * ataşare la scadență → reîncărcare. Descărcarea folosește un URL temporar semnat,
 * cu autorizare la nivel de obiect pe server.
 */
export function DocumentControl({ deadline, onChange }: { deadline: Deadline; onChange: () => void }) {
  const t = useT();
  const inputRef = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const doc = deadline.document;

  async function onPick(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    e.target.value = ''; // permite re-selectarea aceluiași fișier
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
      await api.attachDocument(deadline.id, uploaded.id);
      onChange();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : t('Încărcare eșuată.'));
    } finally {
      setBusy(false);
    }
  }

  function download() {
    if (!doc) return;
    // P0-04: descărcarea se autorizează prin scadență (proprietarul
    // vehiculului sau adminul), nu prin cine a încărcat documentul.
    window.open(`/api/deadlines/${deadline.id}/documents/${doc.id}`, '_blank', 'noopener');
  }

  return (
    <div className="stack" style={{ gap: 6 }}>
      {doc ? (
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
          <span aria-hidden>📎</span>
          <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{doc.originalName ?? t('document')}</span>
          <span className="muted" style={{ fontSize: '0.78rem' }}>
            {formatSize(doc.sizeBytes)}
          </span>
          {doc.scanStatus === 'PENDING' ? (
            <span className="badge badge-unknown">
              <span aria-hidden>•</span> {t('în curs de scanare')}
            </span>
          ) : null}
          {doc.scanStatus === 'INFECTED' ? (
            <span className="badge badge-err">
              <span aria-hidden>×</span> {t('respins (malware)')}
            </span>
          ) : null}
          {doc.servable ? (
            <button
              className="btn btn-ghost"
              style={{ width: 'auto', padding: '6px 12px' }}
              disabled={busy}
              onClick={download}
            >
              {busy ? '…' : t('Descarcă')}
            </button>
          ) : null}
        </div>
      ) : (
        <span className="muted" style={{ fontSize: '0.85rem' }}>
          {t('Niciun document ataşat.')}
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
          {busy ? t('Se încarcă…') : doc ? t('Înlocuieşte documentul') : t('📎 Ataşează document')}
        </button>
        <span className="muted" style={{ fontSize: '0.75rem', marginLeft: 8 }}>
          {t('JPG, PNG, WEBP sau PDF · max {n} MB', { n: Math.round(UPLOAD_MAX_BYTES / (1024 * 1024)) })}
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
