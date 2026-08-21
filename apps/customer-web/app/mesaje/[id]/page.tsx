'use client';

import Link from 'next/link';
import { Icon } from '@/components/Icon';
import { useCallback, useEffect, useState } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { api, conversationDocumentHref } from '@/lib/api';
import { ApiError, type Conversation } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { Loading, ErrorState } from '@/components/states';

export default function ConversationThreadPage() {
  const router = useRouter();
  const t = useT();
  const params = useParams<{ id: string }>();
  const [conv, setConv] = useState<Conversation | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [body, setBody] = useState('');
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
      await api.postMessage(params.id, { body });
      setBody('');
      load();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Trimitere eșuată.');
    } finally {
      setBusy(false);
    }
  }

  if (error && conv === null) return <ErrorState message={t(error)} onRetry={load} />;
  if (conv === null) return <Loading rows={4} />;

  return (
    <>
      <header className="page-head">
        <div>
  <Link href="/mesaje" className="back-link">
          <Icon name="arrow-left" size={14} />
          {t('Mesaje')}
        </Link>
          <h1>{conv.subject}</h1>
        </div>
      </header>
      <div className="muted" style={{ fontSize: 'var(--text-sm)', marginBottom: 12 }}>
        {t(conv.statusLabel)}
        {conv.vehiclePlate ? ` · ${conv.vehiclePlate}` : ''}
      </div>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <div className="stack" style={{ gap: 10 }}>
        {(conv.messages ?? []).map((m) => (
          <div
            key={m.id}
            className="card"
            style={{ borderLeft: `3px solid ${m.authorRole === 'ADMIN' ? 'var(--accent, #0a2540)' : 'var(--border)'}` }}
          >
            <div className="muted" style={{ fontSize: 'var(--text-xs)', marginBottom: 4 }}>
              {t(m.authorLabel)} · {new Date(m.createdAt).toLocaleString('ro-RO')}
            </div>
            <div style={{ whiteSpace: 'pre-wrap' }}>{m.body}</div>
            {m.attachments.length > 0 ? (
              <div className="stack" style={{ gap: 4, marginTop: 6 }}>
                {m.attachments.map((d) => (
                  <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                    <Icon name="paperclip" size={14} />
                    <span style={{ fontSize: 'var(--text-sm)', wordBreak: 'break-all' }}>{d.originalName ?? t('document')}</span>
                    {d.servable ? (
                      <a
                        className="btn btn-ghost"
                        style={{ width: 'auto', padding: '4px 10px' }}
                        href={conversationDocumentHref(conv.id, d.id)}
                        target="_blank"
                        rel="noopener"
                      >
                        {t('Descarcă')}
                      </a>
                    ) : (
                      <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('în curs de scanare')}</span>
                    )}
                  </div>
                ))}
              </div>
            ) : null}
          </div>
        ))}
      </div>

      {conv.status === 'CLOSED' ? (
        <div className="card muted" style={{ marginTop: 16 }}>{t('Conversație închisă de service. Nu se mai pot trimite mesaje.')}</div>
      ) : (
      <>
      <h2 style={{ marginTop: 16 }}>{t('Răspunde')}</h2>
      <form onSubmit={send} className="panel panel-body panel-form stack">
        <textarea
          value={body}
          onChange={(e) => setBody(e.target.value)}
          rows={3}
          required
          placeholder={t('Scrieți un mesaj…')}
          style={{ width: '100%', padding: 12, border: '1px solid var(--border)', borderRadius: 8, fontSize: 'var(--text-md)', background: '#fff' }}
        />
        <button className="btn" type="submit" disabled={busy}>
          {busy ? t('Se trimite…') : t('Trimite')}
        </button>
      </form>
      </>
      )}
    </>
  );
}
