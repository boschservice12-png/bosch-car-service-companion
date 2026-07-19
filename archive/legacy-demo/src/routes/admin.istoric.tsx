import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader, EmptyState } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { StatusBadge } from "@/components/status-badge";
import { fmtDate, fmtRON } from "@/lib/format";
import { useAuth } from "@/lib/auth";
import { toast } from "sonner";

export const Route = createFileRoute("/admin/istoric")({ component: Page });

function Page() {
  const data = useData();
  const [vid, setVid] = useState<string>(data.vehicles[0]?.id ?? "");
  const entries = data.serviceHistory.filter((s) => s.vehicleId === vid).sort((a, b) => a.data.localeCompare(b.data));
  const veh = data.vehicles.find((v) => v.id === vid);
  const client = data.clients.find((c) => c.id === veh?.clientId);

  return (
    <div>
      <PageHeader title="Istoric service" description="Istoric publicat: modificările se fac exclusiv prin înregistrare de corecție." action={<AddEntry vid={vid} />} />
      <div className="mb-4 max-w-md">
        <Label>Vehicul</Label>
        <Select value={vid} onValueChange={setVid}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>{data.vehicles.map((v) => {
            const c = data.clients.find((x) => x.id === v.clientId);
            return <SelectItem key={v.id} value={v.id}>{v.marca} {v.model} — {v.numarInmatriculare} ({c?.prenume} {c?.nume})</SelectItem>;
          })}</SelectContent>
        </Select>
      </div>
      {client && <p className="text-sm text-muted-foreground mb-3">Client: <span className="font-medium text-foreground">{client.prenume} {client.nume}</span></p>}
      {entries.length === 0 ? (
        <EmptyState title="Fără intrări" description="Niciun istoric pentru acest vehicul." />
      ) : (
        <div className="space-y-3">
          {entries.map((e) => (
            <Card key={e.id}>
              <CardHeader className="pb-2">
                <CardTitle className="text-base grid grid-cols-[minmax(0,1fr)_auto] gap-3 items-start">
                  <span className="truncate">{e.tipLucrare}</span>
                  <span className="shrink-0 text-sm font-normal text-muted-foreground">{fmtDate(e.data)} · {e.km.toLocaleString("ro-RO")} km</span>
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <p className="text-sm whitespace-pre-wrap">{e.descriere}</p>
                <div className="flex items-center gap-3 text-sm">
                  {e.cost != null && <span className="text-muted-foreground">{fmtRON(e.cost)}</span>}
                  {e.corectieDe && <StatusBadge tone="warn">Corecție</StatusBadge>}
                  {!e.publicat && <StatusBadge tone="neutral">Nepublicat</StatusBadge>}
                </div>
                {e.motivCorectie && <p className="text-xs text-muted-foreground">Motiv corecție: {e.motivCorectie}</p>}
                {e.publicat && !e.corectieDe && <CorrectionDialog entryId={e.id} vid={vid} />}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}

function AddEntry({ vid }: { vid: string }) {
  const { session } = useAuth();
  const data = useData();
  const [open, setOpen] = useState(false);
  const [f, setF] = useState({ data: new Date().toISOString().slice(0, 10), km: 0, tipLucrare: "", descriere: "", cost: 0 });
  const save = () => {
    if (!vid) return toast.error("Selectați un vehicul.");
    if (!f.tipLucrare || !f.descriere) return toast.error("Completați câmpurile.");
    const v = data.vehicles.find((x) => x.id === vid)!;
    update((d) => d.serviceHistory.push({ id: uid("s"), vehicleId: vid, clientId: v.clientId, ...f, publicat: true, autor: session!.name, creatLa: new Date().toISOString() }));
    audit({ autor: session!.name, rol: "admin", actiune: "Intrare istoric service", entitate: `Vehicul ${vid}`, detalii: f.tipLucrare });
    setOpen(false); toast.success("Intrare publicată.");
    setF({ data: new Date().toISOString().slice(0, 10), km: 0, tipLucrare: "", descriere: "", cost: 0 });
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button disabled={!vid}>Adaugă intrare</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Intrare service</DialogTitle></DialogHeader>
        <div className="grid grid-cols-2 gap-2">
          <div><Label>Data</Label><Input type="date" value={f.data} onChange={(e) => setF({ ...f, data: e.target.value })} /></div>
          <div><Label>Km</Label><Input type="number" value={f.km} onChange={(e) => setF({ ...f, km: Number(e.target.value) })} /></div>
          <div className="col-span-2"><Label>Tip lucrare</Label><Input value={f.tipLucrare} onChange={(e) => setF({ ...f, tipLucrare: e.target.value })} /></div>
          <div className="col-span-2"><Label>Descriere</Label><Textarea rows={4} value={f.descriere} onChange={(e) => setF({ ...f, descriere: e.target.value })} /></div>
          <div><Label>Cost (RON)</Label><Input type="number" value={f.cost} onChange={(e) => setF({ ...f, cost: Number(e.target.value) })} /></div>
        </div>
        <DialogFooter><Button onClick={save}>Publică</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function CorrectionDialog({ entryId, vid }: { entryId: string; vid: string }) {
  const { session } = useAuth();
  const data = useData();
  const original = data.serviceHistory.find((s) => s.id === entryId)!;
  const [open, setOpen] = useState(false);
  const [f, setF] = useState({ tipLucrare: original.tipLucrare, descriere: original.descriere, cost: original.cost ?? 0, motivCorectie: "" });
  const save = () => {
    if (!f.motivCorectie.trim()) return toast.error("Motivul corecției este obligatoriu.");
    const v = data.vehicles.find((x) => x.id === vid)!;
    update((d) => d.serviceHistory.push({ id: uid("s"), vehicleId: vid, clientId: v.clientId, data: new Date().toISOString().slice(0, 10), km: original.km, tipLucrare: f.tipLucrare, descriere: f.descriere, cost: f.cost, publicat: true, corectieDe: entryId, motivCorectie: f.motivCorectie.trim(), autor: session!.name, creatLa: new Date().toISOString() }));
    audit({ autor: session!.name, rol: "admin", actiune: "Corecție istoric", entitate: `Intrare ${entryId}`, detalii: f.motivCorectie });
    setOpen(false); toast.success("Corecție publicată.");
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline">Emite corecție</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Corecție intrare istoric</DialogTitle></DialogHeader>
        <p className="text-xs text-muted-foreground">Această operație creează o intrare nouă legată de original. Originalul rămâne vizibil.</p>
        <div className="space-y-2">
          <div><Label>Tip lucrare (corectat)</Label><Input value={f.tipLucrare} onChange={(e) => setF({ ...f, tipLucrare: e.target.value })} /></div>
          <div><Label>Descriere (corectată)</Label><Textarea rows={4} value={f.descriere} onChange={(e) => setF({ ...f, descriere: e.target.value })} /></div>
          <div><Label>Cost</Label><Input type="number" value={f.cost} onChange={(e) => setF({ ...f, cost: Number(e.target.value) })} /></div>
          <div><Label>Motiv corecție *</Label><Textarea rows={2} value={f.motivCorectie} onChange={(e) => setF({ ...f, motivCorectie: e.target.value })} /></div>
        </div>
        <DialogFooter><Button onClick={save}>Emite corecție</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}