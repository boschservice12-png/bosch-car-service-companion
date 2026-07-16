'use client';

import { useState } from 'react';
import type { ServiceRecord, ServiceRecordInput } from '@/lib/types';

const inputStyle: React.CSSProperties = {
  width: '100%',
  padding: 12,
  border: '1px solid var(--border)',
  borderRadius: 8,
  fontSize: '1rem',
  background: '#fff',
};

/** Formular ciornă de service — folosit atât la creare, cât și la editare/corecție. */
export function ServiceRecordForm({
  initial,
  submitLabel,
  busy,
  onSubmit,
  onCancel,
}: {
  initial?: ServiceRecord;
  submitLabel: string;
  busy: boolean;
  onSubmit: (data: ServiceRecordInput) => void;
  onCancel?: () => void;
}) {
  const [serviceDate, setServiceDate] = useState(initial?.serviceDate ?? '');
  const [odometerKm, setOdometerKm] = useState(initial?.odometerKm != null ? String(initial.odometerKm) : '');
  const [workType, setWorkType] = useState(initial?.workType ?? '');
  const [workDescription, setWorkDescription] = useState(initial?.workDescription ?? '');
  const [partsSummary, setPartsSummary] = useState(initial?.partsSummary ?? '');
  const [laborCost, setLaborCost] = useState(initial ? String(initial.laborCost) : '');
  const [totalAmount, setTotalAmount] = useState(initial ? String(initial.totalAmount) : '');
  const [warranty, setWarranty] = useState(initial?.warranty ?? '');

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const data: ServiceRecordInput = {
      workType,
      workDescription,
      partsSummary,
      warranty,
    };
    if (serviceDate.trim() !== '') data.serviceDate = serviceDate;
    if (odometerKm.trim() !== '') data.odometerKm = Number.parseInt(odometerKm, 10);
    if (laborCost.trim() !== '') data.laborCost = Number.parseFloat(laborCost);
    if (totalAmount.trim() !== '') data.totalAmount = Number.parseFloat(totalAmount);
    onSubmit(data);
  }

  return (
    <form onSubmit={submit} className="card stack" style={{ gap: 10 }}>
      <div className="field">
        <label htmlFor="serviceDate">Data</label>
        <input id="serviceDate" type="date" value={serviceDate} onChange={(e) => setServiceDate(e.target.value)} />
      </div>
      <div className="field">
        <label htmlFor="odometerKm">Kilometraj (km)</label>
        <input id="odometerKm" type="number" min={0} inputMode="numeric" value={odometerKm} onChange={(e) => setOdometerKm(e.target.value)} />
      </div>
      <div className="field">
        <label htmlFor="workType">Tipul lucrării</label>
        <input id="workType" type="text" maxLength={120} value={workType} onChange={(e) => setWorkType(e.target.value)} placeholder="Ex: Revizie periodică" />
      </div>
      <div className="field">
        <label htmlFor="workDescription">Lucrări efectuate</label>
        <textarea id="workDescription" rows={3} value={workDescription} onChange={(e) => setWorkDescription(e.target.value)} style={inputStyle} />
      </div>
      <div className="field">
        <label htmlFor="partsSummary">Piese (rezumat)</label>
        <textarea id="partsSummary" rows={2} value={partsSummary} onChange={(e) => setPartsSummary(e.target.value)} style={inputStyle} />
      </div>
      <div className="field">
        <label htmlFor="laborCost">Manoperă (RON)</label>
        <input id="laborCost" type="number" min={0} step="0.01" inputMode="decimal" value={laborCost} onChange={(e) => setLaborCost(e.target.value)} />
      </div>
      <div className="field">
        <label htmlFor="totalAmount">Total (RON)</label>
        <input id="totalAmount" type="number" min={0} step="0.01" inputMode="decimal" value={totalAmount} onChange={(e) => setTotalAmount(e.target.value)} />
      </div>
      <div className="field">
        <label htmlFor="warranty">Garanție</label>
        <input id="warranty" type="text" maxLength={255} value={warranty} onChange={(e) => setWarranty(e.target.value)} placeholder="Ex: 12 luni / 20.000 km" />
      </div>
      <div style={{ display: 'flex', gap: 8 }}>
        <button className="btn" type="submit" disabled={busy}>
          {busy ? 'Se salvează…' : submitLabel}
        </button>
        {onCancel ? (
          <button className="btn btn-ghost" type="button" onClick={onCancel} disabled={busy}>
            Renunță
          </button>
        ) : null}
      </div>
    </form>
  );
}
