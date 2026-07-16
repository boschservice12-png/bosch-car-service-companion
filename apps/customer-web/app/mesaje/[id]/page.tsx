'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, conversationDocumentHref } from '@/lib/api';
import { ApiError, type Conversation } from '@/lib/types';
import { Loading, ErrorState } from '@/components/states';
import { AttachmentPicker, type PickedFile } from '@/components/AttachmentPicker';

function money(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

export default function ConversationThreadPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [conv, setConv] = useState<Conversation | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [body, setBody] = useState('');
  const [attachments, setAttachments] = useState<PickedFile[]>([]);
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .conversation(params.id)
      .then(setConv)
      .catch((err) => {
        if (err instanceof ApiError && err.httpStatus === 401) {
          router.replace('/login');
          return;
        }
        if (err instanceof ApiError && (err.httpStatus === 403 || err.httpStatus === 404)) {
          setError('Conversația nu este disponibilă.');
          return;
        }
        setError(err instanceof ApiError ? err.problem.title : 'Eroare la încărcare.');
      });
  }, [params.id, router]);

  useEffect(load, [load]);

  async function send(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.postMessage(params.id, { body, documentIds: attachments.map((a) => a.id) });
      setBody('');
      setAttachments([]);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
    } finally {
      setBusy(false);
    }
  }

  async function respond(accept: boolean) {
    setBusy(true);
    try {
      await (accept ? api.acceptQuote(params.id) : api.declineQuote(params.id));
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Operațiune eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && conv === null) return <ErrorState message={error} onRetry={load} />;
  if (conv === null) return <Loading rows={4} />;

  const pendingQuote = conv.type === 'QUOTE' && conv.status === 'QUOTED';

  return (
    <>
      <Link href="/mesaje" className="muted">
        ← Mesaje
      </Link>
      <h1 style={{ marginBottom: 4 }}>{conv.subject}</h1>
      <div className="muted" style={{ fontSize: '0.85rem', marginBottom: 12 }}>
        {conv.typeLabel} · {conv.statusLabel}
        {conv.vehiclePlate ? ` · ${conv.vehiclePlate}` : ''}
      </div>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <div className="stack" style={{ gap: 10 }}>
        {(conv.messages ?? []).map((m) => (
          <div
            key={m.id}
            className="card"
            style={{ borderLeft: `3px solid ${m.authorRole === 'ADMIN' ? 'var(--accent, #0a2540)' : 'var(--border)'}` }}
          >
            <div className="muted" style={{ fontSize: '0.78rem', marginBottom: 4 }}>
              {m.authorLabel} · {new Date(m.createdAt).toLocaleString('ro-RO')}
            </div>
            <div style={{ whiteSpace: 'pre-wrap' }}>{m.body}</div>
            {m.attachments.length > 0 ? (
              <div className="stack" style={{ gap: 4, marginTop: 6 }}>
                {m.attachments.map((d) => (
                  <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                    <span aria-hidden>📎</span>
                    <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{d.originalName ?? 'document'}</span>
                    {d.servable ? (
                      <a
                        className="btn btn-ghost"
                        style={{ width: 'auto', padding: '4px 10px' }}
                        href={conversationDocumentHref(conv.id, d.id)}
                        target="_blank"
                        rel="noopener"
                      >
                        Descarcă
                      </a>
                    ) : (
                      <span className="muted" style={{ fontSize: '0.8rem' }}>în curs de scanare</span>
                    )}
                  </div>
                ))}
              </div>
            ) : null}
          </div>
        ))}
      </div>

      {conv.quoteAmount != null ? (
        <div className="card" style={{ marginTop: 12 }}>
          <strong>Ofertă service: {money(conv.quoteAmount)}</strong>
          {pendingQuote ? (
            <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
              <button className="btn" style={{ width: 'auto', padding: '8px 14px' }} disabled={busy} onClick={() => respond(true)}>
                Acceptă
              </button>
              <button className="btn btn-ghost" style={{ width: 'auto', padding: '8px 14px' }} disabled={busy} onClick={() => respond(false)}>
                Refuză
              </button>
            </div>
          ) : (
            <div className="muted" style={{ fontSize: '0.85rem', marginTop: 4 }}>{conv.statusLabel}</div>
          )}
        </div>
      ) : null}

      <h2 style={{ marginTop: 16 }}>Răspunde</h2>
      <form onSubmit={send} className="card stack" style={{ gap: 10 }}>
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={3}
          required
          placeholder="Scrieți un mesaj…"
          style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: '1rem', background: '#fff' }}
        />
        <AttachmentPicker files={attachments} onChange={setAttachments} />
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se trimite…' : 'Trimite'}
        </button>
      </form>
    </>
  );
}
