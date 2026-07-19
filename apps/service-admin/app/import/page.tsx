'use client';

import Link from 'next/link';
import { useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, type ImportReport } from '@/lib/types';

/**
 * Import clienți + vehicule din Excel (.xlsx) sau CSV.
 * Coloane așteptate: Proprietar, Număr înmatriculare, VIN, Marcă, Model,
 * opțional Telefon și Email (antetul poate fi în orice ordine).
 */
export default function ImportPage() {
  const [file, setFile] = useState<File | null>(null);
  const [report, setReport] = useState<ImportReport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!file) return;
    setBusy(true);
    setError(null);
    setReport(null);
    try {
      setReport(await api.importClients(file));
    } catch (err) {
      setError(err instanceof ApiError ? err.problem.title : 'Importul a eșuat.');
      if (err instanceof ApiError && err.problem.errors) {
        const fieldErrors = Object.values(err.problem.errors).flat();
        if (fieldErrors.length > 0) setError(fieldErrors.join(' '));
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <Link href="/" className="muted">
        ← Panou
      </Link>
      <h1>Import clienți din Excel</h1>
      <p className="muted">
        Încărcați tabelul cu proprietari și vehicule (.xlsx sau .csv). Coloane: <b>Proprietar</b>,{' '}
        <b>Număr înmatriculare</b>, <b>VIN</b>, <b>Marcă</b>, <b>Model</b>, opțional <b>Telefon</b> și <b>Email</b>.
        Reimportul aceluiași fișier nu creează dubluri.
      </p>

      {error ? <div className="alert alert-err" role="alert">{error}</div> : null}

      <form onSubmit={submit} className="card stack" style={{ gap: 12 }}>
        <div className="field">
          <label htmlFor="file">Fișier (.xlsx sau .csv, max. 5 MB)</label>
          <input
            id="file"
            type="file"
            accept=".xlsx,.csv"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            required
          />
        </div>
        <button className="btn" type="submit" disabled={busy || !file}>
          {busy ? 'Se importă…' : 'Importă'}
        </button>
      </form>

      {report ? (
        <>
          <h2 style={{ marginTop: 16 }}>Raport import</h2>
          <div className="card stack" style={{ gap: 6 }}>
            <div><span className="muted">Rânduri procesate:</span> <b>{report.totalRows}</b></div>
            <div><span className="muted">Proprietari noi:</span> <b>{report.ownersCreated}</b></div>
            <div><span className="muted">Vehicule noi:</span> <b>{report.vehiclesCreated}</b> · <span className="muted">actualizate:</span> <b>{report.vehiclesUpdated}</b></div>
            <div><span className="muted">Legături proprietate create:</span> <b>{report.ownershipsCreated}</b></div>
          </div>

          {report.errors.length > 0 ? (
            <>
              <h3 style={{ marginTop: 12 }}>Rânduri cu probleme ({report.errors.length})</h3>
              <div className="stack" style={{ gap: 6 }}>
                {report.errors.map((e) => (
                  <div key={`${e.row}-${e.message}`} className="alert alert-err" style={{ fontSize: '0.88rem' }}>
                    Rândul {e.row}: {e.message}
                  </div>
                ))}
              </div>
            </>
          ) : (
            <p className="muted" style={{ marginTop: 8 }}>Toate rândurile au fost importate fără erori. ✔</p>
          )}
        </>
      ) : null}
    </>
  );
}
