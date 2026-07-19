'use client';

import Link from 'next/link';
import { useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, type HistoryImportReport, type ImportReport } from '@/lib/types';

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
  const [histFile, setHistFile] = useState<File | null>(null);
  const [histReport, setHistReport] = useState<HistoryImportReport | null>(null);
  const [histError, setHistError] = useState<string | null>(null);
  const [histBusy, setHistBusy] = useState(false);

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

  async function submitHistory(e: React.FormEvent) {
    e.preventDefault();
    if (!histFile) return;
    setHistBusy(true);
    setHistError(null);
    setHistReport(null);
    try {
      setHistReport(await api.importServiceHistory(histFile));
    } catch (err) {
      setHistError(err instanceof ApiError ? err.problem.title : 'Importul a eșuat.');
      if (err instanceof ApiError && err.problem.errors) {
        const fieldErrors = Object.values(err.problem.errors).flat();
        if (fieldErrors.length > 0) setHistError(fieldErrors.join(' '));
      }
    } finally {
      setHistBusy(false);
    }
  }

  return (
    <>
      <Link href="/" className="muted">
        ← Panou
      </Link>
      <h1>Import date din Excel</h1>
      <h2 style={{ marginTop: 8 }}>Pasul 1 — Clienți și vehicule</h2>
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

      <h2 style={{ marginTop: 28 }}>Pasul 2 — Istoric reparații</h2>
      <p className="muted">
        Tabelul cu istoricul de reparații, legat de vehicule prin <b>VIN</b> (importați întâi clienții).
        Coloane: <b>VIN</b>, <b>Dată</b>, <b>Kilometraj</b>, <b>Lucrare</b>, <b>Descriere</b>, <b>Piese</b>,{' '}
        <b>Manoperă</b>, <b>Total</b>, <b>Garanție</b>, <b>Număr comandă</b>. Rândurile complete devin vizibile
        clientului imediat; cele incomplete rămân ciorne. Reimportul nu creează dubluri.
      </p>

      {histError ? <div className="alert alert-err" role="alert">{histError}</div> : null}

      <form onSubmit={submitHistory} className="card stack" style={{ gap: 12 }}>
        <div className="field">
          <label htmlFor="histFile">Fișier istoric (.xlsx sau .csv, max. 5 MB)</label>
          <input
            id="histFile"
            type="file"
            accept=".xlsx,.csv"
            onChange={(e) => setHistFile(e.target.files?.[0] ?? null)}
            required
          />
        </div>
        <button className="btn" type="submit" disabled={histBusy || !histFile}>
          {histBusy ? 'Se importă…' : 'Importă istoricul'}
        </button>
      </form>

      {histReport ? (
        <>
          <h3 style={{ marginTop: 16 }}>Raport istoric</h3>
          <div className="card stack" style={{ gap: 6 }}>
            <div><span className="muted">Rânduri procesate:</span> <b>{histReport.totalRows}</b></div>
            <div><span className="muted">Publicate (vizibile clientului):</span> <b>{histReport.recordsPublished}</b></div>
            <div><span className="muted">Ciorne (de completat):</span> <b>{histReport.recordsDraft}</b></div>
            <div><span className="muted">Sărite (existau deja):</span> <b>{histReport.recordsSkipped}</b></div>
          </div>
          {histReport.errors.length > 0 ? (
            <div className="stack" style={{ gap: 6, marginTop: 10 }}>
              {histReport.errors.map((e) => (
                <div key={`${e.row}-${e.message}`} className="alert alert-err" style={{ fontSize: '0.88rem' }}>
                  Rândul {e.row}: {e.message}
                </div>
              ))}
            </div>
          ) : (
            <p className="muted" style={{ marginTop: 8 }}>Toate rândurile au fost importate fără erori. ✔</p>
          )}
        </>
      ) : null}
    </>
  );
}
