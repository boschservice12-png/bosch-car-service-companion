'use client';

import { serviceRecordDocumentHref } from '@/lib/api';
import type { ServiceRecord } from '@/lib/types';

export function formatMoney(ron: number): string {
  return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON' }).format(ron);
}

function formatKm(km: number | null): string {
  return km === null ? '—' : `${new Intl.NumberFormat('ro-RO').format(km)} km`;
}

/** Afișare read-only a unei înregistrări de service (folosită de client). */
export function ServiceRecordView({ record }: { record: ServiceRecord }) {
  return (
    <div className="card stack" style={{ gap: 8 }}>
      <div className="list-row">
        <div>
          <strong>{record.serviceDate ?? '—'}</strong>
          <div className="muted" style={{ fontSize: '0.82rem' }}>
            {record.workType ?? 'Lucrare'} · {formatKm(record.odometerKm)}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
          {record.correctionOfId ? <span className="badge badge-warn">✎ Corecție</span> : null}
          {record.correctionOfId && record.correctionReason ? (
            <span className="muted" style={{ fontSize: '0.8rem' }}>Motiv: {record.correctionReason}</span>
          ) : null}
          {record.corrected ? <span className="badge badge-unknown">• Corectat ulterior</span> : null}
        </div>
      </div>

      {record.workDescription ? <p style={{ margin: 0 }}>{record.workDescription}</p> : null}

      {record.partsSummary ? (
        <div>
          <span className="muted" style={{ fontSize: '0.82rem' }}>Piese:</span>{' '}
          <span style={{ fontSize: '0.9rem' }}>{record.partsSummary}</span>
        </div>
      ) : null}

      <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap', fontSize: '0.9rem' }}>
        <span>
          <span className="muted">Manoperă:</span> {formatMoney(record.laborCost)}
        </span>
        <span>
          <strong>Total:</strong> {formatMoney(record.totalAmount)}
        </span>
        {record.warranty ? (
          <span>
            <span className="muted">Garanție:</span> {record.warranty}
          </span>
        ) : null}
      </div>

      {record.documents.length > 0 ? (
        <div className="stack" style={{ gap: 4 }}>
          <span className="muted" style={{ fontSize: '0.82rem' }}>Documente:</span>
          {record.documents.map((d) => (
            <div key={d.id} style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <span aria-hidden>📎</span>
              <span style={{ fontSize: '0.9rem', wordBreak: 'break-all' }}>{d.originalName ?? 'document'}</span>
              {d.servable ? (
                <a
                  className="btn btn-ghost"
                  style={{ width: 'auto', padding: '4px 10px' }}
                  href={serviceRecordDocumentHref(record.id, d.id)}
                  target="_blank"
                  rel="noopener"
                >
                  Descarcă
                </a>
              ) : (
                <span className="muted" style={{ fontSize: '0.8rem' }}>indisponibil</span>
              )}
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
