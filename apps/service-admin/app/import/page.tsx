'use client';

import Link from 'next/link';
import { useState } from 'react';
import { api } from '@/lib/api';
import { ApiError, type DeadlineImportReport, type HistoryImportReport, type ImportReport } from '@/lib/types';
import { useT } from '@/lib/i18n';

/**
 * Import clienți + vehicule din Excel (.xlsx) sau CSV.
 * Coloane așteptate: Proprietar, Număr înmatriculare, VIN, Marcă, Model,
 * opțional Telefon și Email (antetul poate fi în orice ordine).
 */
export default function ImportPage() {
  const t = useT();
  const [file, setFile] = useState<File | null>(null);
  const [report, setReport] = useState<ImportReport | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [histFile, setHistFile] = useState<File | null>(null);
  const [histReport, setHistReport] = useState<HistoryImportReport | null>(null);
  const [histError, setHistError] = useState<string | null>(null);
  const [histBusy, setHistBusy] = useState(false);
  const [dlFile, setDlFile] = useState<File | null>(null);
  const [dlReport, setDlReport] = useState<DeadlineImportReport | null>(null);
  const [dlError, setDlError] = useState<string | null>(null);
  const [dlBusy, setDlBusy] = useState(false);

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

  async function submitDeadlines(e: React.FormEvent) {
    e.preventDefault();
    if (!dlFile) return;
    setDlBusy(true);
    setDlError(null);
    setDlReport(null);
    try {
      setDlReport(await api.importDeadlines(dlFile));
    } catch (err) {
      setDlError(err instanceof ApiError ? err.problem.title : 'Importul a eșuat.');
      if (err instanceof ApiError && err.problem.errors) {
        const fieldErrors = Object.values(err.problem.errors).flat();
        if (fieldErrors.length > 0) setDlError(fieldErrors.join(' '));
      }
    } finally {
      setDlBusy(false);
    }
  }

  return (
    <>
      <Link href="/" className="muted">
        {t('← Panou')}
      </Link>
      <h1>{t('Import date din Excel')}</h1>
      <h2 style={{ marginTop: 8 }}>{t('Pasul 1 — Clienți și vehicule')}</h2>
      <p className="muted">
        {t('Încărcați „lista parteneri" din ASM (.xls, .xlsx sau .csv). Coloane recunoscute:')} <b>Partener/Proprietar</b>,{' '}
        <b>Nr. înmatriculare</b>, <b>Serie şasiu (VIN)</b>, <b>Tip autovehicul</b> {t('sau')} <b>Marcă</b>+<b>Model</b>,{' '}
        {t('opțional')} <b>Telefon</b>/<b>Mobil</b> {t('și')} <b>E-mail</b>. {t('Reimportul aceluiași fișier nu creează dubluri.')}
      </p>

      {error ? <div className="alert alert-err" role="alert">{t(error)}</div> : null}

      <form onSubmit={submit} className="card stack" style={{ gap: 12 }}>
        <div className="field">
          <label htmlFor="file">{t('Fișier (.xls, .xlsx sau .csv, max. 10 MB)')}</label>
          <input
            id="file"
            type="file"
            accept=".xls,.xlsx,.csv"
            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
            required
          />
        </div>
        <button className="btn" type="submit" disabled={busy || !file}>
          {busy ? t('Se importă…') : t('Importă')}
        </button>
      </form>

      {report ? (
        <>
          <h2 style={{ marginTop: 16 }}>{t('Raport import')}</h2>
          <div className="card stack" style={{ gap: 6 }}>
            <div><span className="muted">{t('Rânduri procesate:')}</span> <b>{report.totalRows}</b></div>
            <div><span className="muted">{t('Proprietari noi:')}</span> <b>{report.ownersCreated}</b></div>
            <div><span className="muted">{t('Vehicule noi:')}</span> <b>{report.vehiclesCreated}</b> · <span className="muted">{t('actualizate:')}</span> <b>{report.vehiclesUpdated}</b></div>
            <div><span className="muted">{t('Legături proprietate create:')}</span> <b>{report.ownershipsCreated}</b></div>
            {report.vinInvalidSkipped ? (
              <div><span className="muted">{t('Sărite — VIN nevalid (rămân doar în ASM):')}</span> <b>{report.vinInvalidSkipped}</b></div>
            ) : null}
            {report.rowsWithoutVehicle ? (
              <div><span className="muted">{t('Parteneri fără vehicul:')}</span> <b>{report.rowsWithoutVehicle}</b></div>
            ) : null}
          </div>

          {report.errors.length > 0 ? (
            <>
              <h3 style={{ marginTop: 12 }}>{t('Rânduri cu probleme ({n})', { n: report.errors.length })}</h3>
              <div className="stack" style={{ gap: 6 }}>
                {report.errors.map((e) => (
                  <div key={`${e.row}-${e.message}`} className="alert alert-err" style={{ fontSize: '0.88rem' }}>
                    {t('Rândul {n}:', { n: e.row })} {e.message}
                  </div>
                ))}
              </div>
            </>
          ) : (
            <p className="muted" style={{ marginTop: 8 }}>{t('Toate rândurile au fost importate fără erori. ✔')}</p>
          )}
        </>
      ) : null}

      <h2 style={{ marginTop: 28 }}>{t('Pasul 2 — Istoric reparații → carte service')}</h2>
      <p className="muted">
        {t('Exportul „PersonalManopere" din ASM sau orice tabel legat prin VIN (importați întâi clienții). Coloane recunoscute:')}{' '}
        <b>Serie şasiu (VIN)</b>, <b>Data</b>, <b>Tip interventie/Lucrare</b>, {t('opțional')} <b>Kilometraj</b>, <b>Descriere/Observații</b>,{' '}
        <b>Total</b>, <b>Număr comandă</b>.{' '}
        {t('Rândurile complete devin vizibile clientului imediat; cele incomplete rămân ciorne. Reimportul nu creează dubluri.')}
      </p>

      {histError ? <div className="alert alert-err" role="alert">{t(histError)}</div> : null}

      <form onSubmit={submitHistory} className="card stack" style={{ gap: 12 }}>
        <div className="field">
          <label htmlFor="histFile">{t('Fișier istoric (.xls, .xlsx sau .csv, max. 10 MB)')}</label>
          <input
            id="histFile"
            type="file"
            accept=".xls,.xlsx,.csv"
            onChange={(e) => setHistFile(e.target.files?.[0] ?? null)}
            required
          />
        </div>
        <button className="btn" type="submit" disabled={histBusy || !histFile}>
          {histBusy ? t('Se importă…') : t('Importă istoricul')}
        </button>
      </form>

      {histReport ? (
        <>
          <h3 style={{ marginTop: 16 }}>{t('Raport istoric')}</h3>
          <div className="card stack" style={{ gap: 6 }}>
            <div><span className="muted">{t('Rânduri procesate:')}</span> <b>{histReport.totalRows}</b></div>
            <div><span className="muted">{t('Publicate (vizibile clientului):')}</span> <b>{histReport.recordsPublished}</b></div>
            <div><span className="muted">{t('Ciorne (de completat):')}</span> <b>{histReport.recordsDraft}</b></div>
            <div><span className="muted">{t('Sărite (existau deja):')}</span> <b>{histReport.recordsSkipped}</b></div>
          </div>
          {histReport.errors.length > 0 ? (
            <div className="stack" style={{ gap: 6, marginTop: 10 }}>
              {histReport.errors.map((e) => (
                <div key={`${e.row}-${e.message}`} className="alert alert-err" style={{ fontSize: '0.88rem' }}>
                  {t('Rândul {n}:', { n: e.row })} {e.message}
                </div>
              ))}
            </div>
          ) : (
            <p className="muted" style={{ marginTop: 8 }}>{t('Toate rândurile au fost importate fără erori. ✔')}</p>
          )}
        </>
      ) : null}

      <h2 style={{ marginTop: 28 }}>{t('Pasul 3 — Report ITP/RCA → scadențe')}</h2>
      <p className="muted">
        {t('Raportul de alerte din ASM („report itp . rca"). Coloane recunoscute:')} <b>Denumire alertă</b>,{' '}
        <b>Data alertei</b>, <b>Nr. înmatriculare</b>.{' '}
        {t('Doar alertele ITP/RCA devin scadențe (restul se sar); vehiculul se identifică după număr, iar reimportul actualizează fără dubluri. Scadențele apar apoi în Alerte (client) și 🔔 Notificări.')}
      </p>

      {dlError ? <div className="alert alert-err" role="alert">{t(dlError)}</div> : null}

      <form onSubmit={submitDeadlines} className="card stack" style={{ gap: 12 }}>
        <div className="field">
          <label htmlFor="dlFile">{t('Fișier raport (.xls, .xlsx sau .csv, max. 10 MB)')}</label>
          <input
            id="dlFile"
            type="file"
            accept=".xls,.xlsx,.csv"
            onChange={(e) => setDlFile(e.target.files?.[0] ?? null)}
            required
          />
        </div>
        <button className="btn" type="submit" disabled={dlBusy || !dlFile}>
          {dlBusy ? t('Se importă…') : t('Importă scadențele')}
        </button>
      </form>

      {dlReport ? (
        <>
          <h3 style={{ marginTop: 16 }}>{t('Raport scadențe')}</h3>
          <div className="card stack" style={{ gap: 6 }}>
            <div><span className="muted">{t('Rânduri procesate:')}</span> <b>{dlReport.totalRows}</b></div>
            <div><span className="muted">{t('Scadențe create:')}</span> <b>{dlReport.deadlinesCreated}</b> · <span className="muted">{t('actualizate:')}</span> <b>{dlReport.deadlinesUpdated}</b></div>
            <div><span className="muted">{t('Neschimbate (sărite):')}</span> <b>{dlReport.rowsSkipped}</b> · <span className="muted">{t('alte alerte (nu ITP/RCA):')}</span> <b>{dlReport.nonItpRcaSkipped}</b></div>
          </div>
          {dlReport.errors.length > 0 ? (
            <div className="stack" style={{ gap: 6, marginTop: 10 }}>
              {dlReport.errors.map((e) => (
                <div key={`${e.row}-${e.message}`} className="alert alert-err" style={{ fontSize: '0.88rem' }}>
                  {t('Rândul {n}:', { n: e.row })} {e.message}
                </div>
              ))}
            </div>
          ) : (
            <p className="muted" style={{ marginTop: 8 }}>{t('Toate rândurile au fost importate fără erori. ✔')}</p>
          )}
        </>
      ) : null}
    </>
  );
}
