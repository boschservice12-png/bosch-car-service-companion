'use client';

import { useState } from 'react';
import { Icon } from '@/components/Icon';
import { api, serviceRecordDocumentHref } from '@/lib/api';
import { ApiError, type ServiceRecord, type ServiceRecordInput } from '@/lib/types';
import { useT } from '@/lib/i18n';
import { ServiceRecordForm } from './ServiceRecordForm';
import { ServiceRecordDocAttach } from './ServiceRecordDocAttach';

function formatMoney(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

/** Card de administrare pentru o înregistrare de service (ciornă sau publicată). */
export function ServiceRecordAdminCard({ record, onChanged }: { record: ServiceRecord; onChanged: () => void }) {
  const t = useT();
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const isDraft = record.status === 'DRAFT';

  async function run(fn: () => Promise<unknown>, after?: () => void) {
    setError(null);
    setBusy(true);
    try {
      await fn();
      after?.();
      onChanged();
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : t('Operațiune eșuată.'));
    } finally {
      setBusy(false);
    }
  }

  function save(data: ServiceRecordInput) {
    run(() => api.updateServiceRecord(record.id, data), () => setEditing(false));
  }

  if (editing) {
    return (
      <div className="stack" style={{ gap: 6 }}>
        <strong>{t('Editează ciorna')}</strong>
        <ServiceRecordForm initial={record} submitLabel={t('Salvează')} busy={busy} onSubmit={save} onCancel={() => setEditing(false)} />
        {error ? <div className="alert alert-err" role="alert">{error}</div> : null}
      </div>
    );
  }

  return (
    <div className="card stack" style={{ gap: 8 }}>
      <div className="list-row">
        <div>
          <strong>{record.serviceDate ?? '—'}</strong>
          <div className="muted" style={{ fontSize: 'var(--text-sm)' }}>
            {record.workType ?? t('Lucrare')} · {record.odometerKm != null ? `${record.odometerKm} km` : '—'}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
          <span className={`badge ${isDraft ? 'badge-warn' : record.status === 'CORRECTED' ? 'badge-unknown' : 'badge-ok'}`}>
            <Icon name={isDraft ? 'edit' : 'check'} size={13} /> {t(record.statusLabel)}
          </span>
          {record.correctionOfId ? <span className="badge badge-unknown">{t('corecție')}</span> : null}
          {record.corrected ? <span className="badge badge-unknown">{t('corectat')}</span> : null}
        </div>
      </div>

      {record.workDescription ? <p style={{ margin: 0 }}>{record.workDescription}</p> : null}
      {record.partsSummary ? (
        <div style={{ fontSize: 'var(--text-sm)' }}>
          <span className="muted">{t('Piese:')}</span> {record.partsSummary}
        </div>
      ) : null}
      <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', fontSize: 'var(--text-sm)' }}>
        <span>
          <span className="muted">{t('Manoperă:')}</span> {formatMoney(record.laborCost)}
        </span>
        <span>
          <strong>{t('Total:')}</strong> {formatMoney(record.totalAmount)}
        </span>
        {record.warranty ? (
          <span>
            <span className="muted">{t('Garanție:')}</span> {record.warranty}
          </span>
        ) : null}
      </div>

      {record.documents.length > 0 ? (
        <div className="stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('Documente:')}</span>
          {record.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <Icon name="paperclip" size={14} />
              <span style={{ fontSize: 'var(--text-sm)', wordBreak: 'break-all' }}>{d.originalName ?? t('document')}</span>
              {d.servable ? (
                <a className="btn btn-ghost" style={{ width: 'auto', padding: '4px 10px' }} href={serviceRecordDocumentHref(record.id, d.id)} target="_blank" rel="noopener">
                  {t('Descarcă')}
                </a>
              ) : (
                <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('în curs de scanare')}</span>
              )}
            </div>
          ))}
        </div>
      ) : null}

      {isDraft ? <ServiceRecordDocAttach recordId={record.id} onChange={onChanged} /> : null}

      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        {isDraft ? (
          <>
            <button className="btn btn-ghost" style={{ width: 'auto', padding: '6px 12px' }} disabled={busy} onClick={() => setEditing(true)}>
              {t('Editează')}
            </button>
            <button className="btn" style={{ width: 'auto', padding: '6px 12px' }} disabled={busy} onClick={() => run(() => api.publishServiceRecord(record.id))}>
              {busy ? '…' : t('Publică')}
            </button>
          </>
        ) : (
          <button className="btn btn-ghost" style={{ width: 'auto', padding: '6px 12px' }} disabled={busy} onClick={() => {
              const reason = window.prompt(t('Motivul corecției (obligatoriu):'));
              if (reason && reason.trim() !== '') run(() => api.correctServiceRecord(record.id, reason.trim()));
            }}>
            {busy ? '…' : t('Creează corecție')}
          </button>
        )}
      </div>

      {error ? <div className="alert alert-err" role="alert" style={{ fontSize: 'var(--text-sm)' }}>{error}</div> : null}
    </div>
  );
}
