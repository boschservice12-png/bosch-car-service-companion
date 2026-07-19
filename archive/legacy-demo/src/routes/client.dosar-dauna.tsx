import { createFileRoute } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { useData, update, audit, uid, fileToDataUrl } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatusBadge } from "@/components/status-badge";
import { fmtDate, fmtDateTime } from "@/lib/format";
import { useState } from "react";
import { toast } from "sonner";

export const Route = createFileRoute("/client/dosar-dauna")({ component: Page });

const TONE = { deschis: "info", documente_lipsa: "warn", trimis_asigurator: "info", aprobat: "ok", respins: "danger", inchis: "neutral" } as const;
const LABEL = { deschis: "Deschis", documente_lipsa: "Documente lipsă", trimis_asigurator: "Trimis la asigurător", aprobat: "Aprobat", respins: "Respins", inchis: "Închis" };

function Page() {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const [vid, setVid] = useState(vehicles[0]?.id ?? "");
  const [dInc, setDInc] = useState("");
  const [loc, setLoc] = useState("");
  const [desc, setDesc] = useState("");
  const [asig, setAsig] = useState("");
  const dosare = data.damages.filter((d) => d.clientId === session?.clientId).sort((a, b) => b.creatLa.localeCompare(a.creatLa));

  const trimite = () => {
    if (!vid || !dInc || !loc.trim() || !desc.trim() || !asig.trim()) return toast.error("Completați toate câmpurile.");
    update((d) => {
      d.damages.push({ id: uid("dm"), clientId: session!.clientId!, vehicleId: vid, dataIncident: dInc, locatie: loc.trim(), descriere: desc.trim(), asigurator: asig.trim(), status: "deschis", pasi: [{ data: new Date().toISOString().slice(0, 10), text: "Dosar deschis de client.", autor: session!.name }], documente: [], creatLa: new Date().toISOString() });
    });
    audit({ autor: session!.name, rol: "client", actiune: "Dosar daună deschis", entitate: `Vehicul ${vid}` });
    setDInc(""); setLoc(""); setDesc(""); setAsig("");
    toast.success("Dosar deschis. Service-ul vă asistă mai departe.");
  };

  const uploadDoc = async (dosarId: string, files: FileList | null) => {
    if (!files?.length) return;
    const items: { nume: string; url: string }[] = [];
    for (const f of Array.from(files)) {
      if (f.size > 2 * 1024 * 1024) { toast.error(`${f.name} depășește 2MB.`); continue; }
      items.push({ nume: f.name, url: await fileToDataUrl(f) });
    }
    if (!items.length) return;
    update((d) => {
      const dos = d.damages.find((x) => x.id === dosarId);
      if (dos) {
        dos.documente.push(...items);
        dos.pasi.push({ data: new Date().toISOString().slice(0, 10), text: `Încărcate ${items.length} document(e).`, autor: session!.name });
      }
    });
    audit({ autor: session!.name, rol: "client", actiune: "Documente încărcate", entitate: `Dosar ${dosarId}` });
    toast.success("Documente încărcate.");
  };

  return (
    <div>
      <PageHeader title="Asistență la deschiderea dosarului de daună" description="Ghidăm pas cu pas dosarul dvs. de daună. Încărcați documentele și urmăriți evoluția." />
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle>Deschide dosar nou</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <div><Label>Mașină</Label>
              <Select value={vid} onValueChange={setVid}><SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{vehicles.map((v) => <SelectItem key={v.id} value={v.id}>{v.marca} {v.model} — {v.numarInmatriculare}</SelectItem>)}</SelectContent>
              </Select>
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div><Label>Data incidentului</Label><Input type="date" value={dInc} onChange={(e) => setDInc(e.target.value)} /></div>
              <div><Label>Asigurător</Label><Input value={asig} onChange={(e) => setAsig(e.target.value)} placeholder="Ex: Allianz-Țiriac" /></div>
            </div>
            <div><Label>Locația incidentului</Label><Input value={loc} onChange={(e) => setLoc(e.target.value)} /></div>
            <div><Label>Descriere</Label><Textarea rows={4} value={desc} onChange={(e) => setDesc(e.target.value)} /></div>
            <Button onClick={trimite}>Deschide dosarul</Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Dosarele mele</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {dosare.length === 0 && <p className="text-sm text-muted-foreground">Niciun dosar deschis.</p>}
            {dosare.map((d) => {
              const v = data.vehicles.find((x) => x.id === d.vehicleId);
              return (
                <div key={d.id} className="border rounded-md p-3 space-y-2">
                  <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                    <div className="min-w-0">
                      <div className="font-medium truncate">{v?.marca} {v?.model} — {v?.numarInmatriculare}</div>
                      <div className="text-xs text-muted-foreground truncate">{d.asigurator}{d.numarDosar ? ` · Nr. ${d.numarDosar}` : ""} · Incident: {fmtDate(d.dataIncident)}</div>
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
                  <div>
                    <Label className="text-xs">Adaugă documente (max 2MB)</Label>
                    <Input type="file" multiple onChange={(e) => uploadDoc(d.id, e.target.files)} />
                    {d.documente.length > 0 && (
                      <ul className="mt-1 text-xs space-y-0.5">
                        {d.documente.map((doc, i) => <li key={i}><a href={doc.url} target="_blank" rel="noreferrer" className="underline">{doc.nume}</a></li>)}
                      </ul>
                    )}
                  </div>
                  <div className="text-xs text-muted-foreground">Creat {fmtDateTime(d.creatLa)}</div>
                </div>
              );
            })}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export { LABEL as DAMAGE_LABEL, TONE as DAMAGE_TONE };