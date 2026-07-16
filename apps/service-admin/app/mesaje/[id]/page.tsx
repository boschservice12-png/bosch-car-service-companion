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

export default function AdminConversationThreadPage() {
  const router = useRouter();
  const params = useParams<{ id: string }>();
  const [conv, setConv] = useState<Conversation | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [body, setBody] = useState('');
  const [attachments, setAttachments] = useState<PickedFile[]>([]);
  const [amount, setAmount] = useState('');
  const [quoteBody, setQuoteBody] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(() => {
    setError(null);
    api
      .conversation(params.id)
      .then(setConv)
      .catch((err) => {
        if (err instanceof ApiError && (err.httpStatus === 401 || err.httpStatus === 403)) {
          router.replace('/login');
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
      await api.reply(params.id, { body, documentIds: attachments.map((a) => a.id) });
      setBody('');
      setAttachments([]);
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
    } finally {
      setBusy(false);
    }
  }

  async function sendQuote(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    try {
      await api.quote(params.id, { amount: Number.parseFloat(amount), body: quoteBody || undefined });
      setAmount('');
      setQuoteBody('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimiterea ofertei a eșuat.');
    } finally {
      setBusy(false);
    }
  }

  if (error && conv === null) return <ErrorState message={error} onRetry={load} />;
  if (conv === null) return <Loading rows={4} />;

  const textareaStyle: React.CSSProperties = {
    width: '100%',
    padding: 12,
    border: '1px solid var(--border)',
    borderRadius: 8,
    fontSize: '1rem',
    background: '#fff',
  };

  return (
    <>
      <Link href="/mesaje" className="muted">
        ← Mesaje
      </Link>
      <h1 style={{ marginBottom: 4 }}>{conv.subject}</h1>
      <div className="muted" style={{ fontSize: '0.85rem', marginBottom: 12 }}>
        {conv.customerName ?? '—'} · {conv.typeLabel} · {conv.statusLabel}
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

      {conv.type === 'QUOTE' ? (
        <>
          <h2 style={{ marginTop: 16 }}>Ofertă</h2>
          <form onSubmit={sendQuote} className="card stack" style={{ gap: 10 }}>
            {conv.quoteAmount != null ? (
              <div className="muted" style={{ fontSize: '0.85rem' }}>Ofertă curentă: {money(conv.quoteAmount)} · {conv.statusLabel}</div>
            ) : null}
            <div className="field">
              <label htmlFor="amount">Sumă ofertă (RON)</label>
              <input id="amount" type="number" min={0} step="0.01" inputMode="decimal" value={amount} onChange={(e) => setAmount(e.target.value)} required />
            </div>
            <textarea value={quoteBody} onChange={(e) => setQuoteBody(e.target.value)} rows={2} placeholder="Detalii ofertă (opțional)…" style={textareaStyle} />
            <button className="btn" type="submit" disabled={busy}>
              {busy ? 'Se trimite…' : 'Trimite oferta'}
            </button>
          </form>
        </>
      ) : null}

      <h2 style={{ marginTop: 16 }}>Răspunde</h2>
      <form onSubmit={send} className="card stack" style={{ gap: 10 }}>
        <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={3} required placeholder="Scrieți un mesaj…" style={textareaStyle} />
        <AttachmentPicker files={attachments} onChange={setAttachments} />
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se trimite…' : 'Trimite'}
        </button>
      </form>
    </>
  );
}
