'use client';

import { serviceRecordDocumentHref } from '@/lib/api';
import { Icon } from '@/components/Icon';
import type { ServiceRecord } from '@/lib/types';
import { useT } from '@/lib/i18n';

export function formatMoney(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

function formatKm(km: number | null): string {
  return km === null ? '—' : `${new Intl.NumberFormat('ro-RO').format(km)} km`;
}

/** Afișare read-only a unei înregistrări de service (folosită de client). */
export function ServiceRecordView({ record }: { record: ServiceRecord }) {
  const t = useT();
  return (
    <div className="card stack" style={{ gap: 8 }}>
      <div className="list-row">
        <div>
          <strong>{record.serviceDate ?? '—'}</strong>
          <div className="muted" style={{ fontSize: 'var(--text-sm)' }}>
            {record.workType ?? t('Lucrare')} · {formatKm(record.odometerKm)}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
          {record.correctionOfId ? <span className="badge badge-warn">{t('Corecție')}</span> : null}
          {record.correctionOfId && record.correctionReason ? (
            <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('Motiv:')} {record.correctionReason}</span>
          ) : null}
          {record.corrected ? <span className="badge badge-unknown">{t('• Corectat ulterior')}</span> : null}
        </div>
      </div>

      {record.workDescription ? <p style={{ margin: 0 }}>{record.workDescription}</p> : null}

      {record.partsSummary ? (
        <div>
          <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('Piese:')}</span>{' '}
          <span style={{ fontSize: 'var(--text-sm)' }}>{record.partsSummary}</span>
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
                <a
                  className="btn btn-ghost"
                  style={{ width: 'auto', padding: '4px 10px' }}
                  href={serviceRecordDocumentHref(record.id, d.id)}
                  target="_blank"
                  rel="noopener"
                >
                  {t('Descarcă')}
                </a>
              ) : (
                <span className="muted" style={{ fontSize: 'var(--text-sm)' }}>{t('indisponibil')}</span>
              )}
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
