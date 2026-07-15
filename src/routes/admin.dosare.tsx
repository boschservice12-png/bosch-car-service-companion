import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData, update, audit } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { StatusBadge } from "@/components/status-badge";
import { fmtDate, fmtDateTime } from "@/lib/format";
import { useAuth } from "@/lib/auth";
import type { DamageStatus } from "@/lib/types";
import { toast } from "sonner";

export const Route = createFileRoute("/admin/dosare")({ component: Page });

const TONE = { deschis: "info", documente_lipsa: "warn", trimis_asigurator: "info", aprobat: "ok", respins: "danger", inchis: "neutral" } as const;
const LABEL = { deschis: "Deschis", documente_lipsa: "Documente lipsă", trimis_asigurator: "Trimis la asigurător", aprobat: "Aprobat", respins: "Respins", inchis: "Închis" };

function Page() {
  const data = useData();
  const list = [...data.damages].sort((a, b) => b.creatLa.localeCompare(a.creatLa));
  return (
    <div>
      <PageHeader title="Dosare daună" description="Asistăm clientul la fiecare pas." />
      <Card><CardContent className="p-0 divide-y">
        {list.map((d) => {
          const c = data.clients.find((x) => x.id === d.clientId);
          const v = data.vehicles.find((x) => x.id === d.vehicleId);
          return (
            <div key={d.id} className="p-4 space-y-2">
              <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                <div className="min-w-0">
                  <div className="font-medium truncate">{c?.prenume} {c?.nume} — {v?.marca} {v?.model} ({v?.numarInmatriculare})</div>
                  <div className="text-xs text-muted-foreground truncate">Incident {fmtDate(d.dataIncident)} · Asigurător: {d.asigurator}{d.numarDosar ? ` · Nr. ${d.numarDosar}` : ""}</div>
                </div>
                <StatusBadge tone={TONE[d.status]}>{LABEL[d.status]}</StatusBadge>
              </div>
              <p className="text-sm">{d.descriere}</p>
              <div className="text-xs">
                <div className="font-medium mb-1">Pași</div>
                <ul className="space-y-1">
                  {d.pasi.map((p, i) => <li key={i}>· {fmtDate(p.data)} — {p.text} <span className="text-muted-foreground">({p.autor})</span></li>)}
                </ul>
              </div>
              {d.documente.length > 0 && (
                <div className="text-xs"><span className="font-medium">Documente:</span>{" "}
                  {d.documente.map((doc, i) => <a key={i} href={doc.url} target="_blank" rel="noreferrer" className="underline mr-2">{doc.nume}</a>)}
                </div>
              )}
              <div className="flex flex-wrap gap-2">
                <SetStatus id={d.id} current={d.status} />
                <AddStep id={d.id} />
                <SetDosarNr id={d.id} current={d.numarDosar} />
              </div>
              <div className="text-xs text-muted-foreground">Deschis {fmtDateTime(d.creatLa)}</div>
            </div>
          );
        })}
        {list.length === 0 && <div className="p-6 text-sm text-muted-foreground">Niciun dosar.</div>}
      </CardContent></Card>
    </div>
  );
}

function SetStatus({ id, current }: { id: string; current: DamageStatus }) {
  const { session } = useAuth();
  return (
    <Select value={current} onValueChange={(v: DamageStatus) => {
      update((d) => { const dm = d.damages.find((x) => x.id === id); if (dm) { dm.status = v; dm.pasi.push({ data: new Date().toISOString().slice(0, 10), text: `Status: ${LABEL[v]}`, autor: session!.name }); } });
      audit({ autor: session!.name, rol: "admin", actiune: "Status dosar schimbat", entitate: `Dosar ${id}`, detalii: v });
    }}>
      <SelectTrigger className="w-52"><SelectValue /></SelectTrigger>
      <SelectContent>{(Object.keys(LABEL) as DamageStatus[]).map((k) => <SelectItem key={k} value={k}>{LABEL[k]}</SelectItem>)}</SelectContent>
    </Select>
  );
}

function AddStep({ id }: { id: string }) {
  const { session } = useAuth();
  const [open, setOpen] = useState(false);
  const [txt, setTxt] = useState("");
  const save = () => {
    if (!txt.trim()) return toast.error("Descrieți pasul.");
    update((d) => { const dm = d.damages.find((x) => x.id === id); if (dm) dm.pasi.push({ data: new Date().toISOString().slice(0, 10), text: txt.trim(), autor: session!.name }); });
    audit({ autor: session!.name, rol: "admin", actiune: "Pas adăugat", entitate: `Dosar ${id}` });
    setOpen(false); setTxt("");
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline">Adaugă pas</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Pas nou dosar</DialogTitle></DialogHeader>
        <Label>Descriere</Label><Textarea rows={3} value={txt} onChange={(e) => setTxt(e.target.value)} />
        <DialogFooter><Button onClick={save}>Salvează</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function SetDosarNr({ id, current }: { id: string; current?: string }) {
  const { session } = useAuth();
  const [open, setOpen] = useState(false);
  const [nr, setNr] = useState(current ?? "");
  const save = () => {
    update((d) => { const dm = d.damages.find((x) => x.id === id); if (dm) dm.numarDosar = nr.trim() || undefined; });
    audit({ autor: session!.name, rol: "admin", actiune: "Nr. dosar actualizat", entitate: `Dosar ${id}`, detalii: nr });
    setOpen(false); toast.success("Salvat.");
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline">Nr. dosar asigurător</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Nr. dosar asigurător</DialogTitle></DialogHeader>
        <Input value={nr} onChange={(e) => setNr(e.target.value)} />
        <DialogFooter><Button onClick={save}>Salvează</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}