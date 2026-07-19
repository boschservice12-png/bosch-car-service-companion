import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { useAuth } from "@/lib/auth";
import { toast } from "sonner";

export const Route = createFileRoute("/admin/vehicule")({ component: Page });

function Page() {
  const data = useData();
  const [q, setQ] = useState("");
  const vs = data.vehicles.filter((v) => `${v.marca} ${v.model} ${v.numarInmatriculare} ${v.vin}`.toLowerCase().includes(q.toLowerCase()));
  return (
    <div>
      <PageHeader title="Vehicule" description="Toate mașinile clienților." action={<AddVehicle />} />
      <div className="mb-3 max-w-sm"><Input placeholder="Caută după marcă, număr, VIN…" value={q} onChange={(e) => setQ(e.target.value)} /></div>
      <Card><CardContent className="p-0">
        <div className="divide-y">
          {vs.map((v) => {
            const c = data.clients.find((x) => x.id === v.clientId);
            return (
              <div key={v.id} className="p-4 grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                <div className="min-w-0">
                  <div className="font-medium truncate">{v.marca} {v.model} ({v.an}) — {v.numarInmatriculare}</div>
                  <div className="text-xs text-muted-foreground truncate">Proprietar: {c?.prenume} {c?.nume} · VIN: {v.vin} · {v.km.toLocaleString("ro-RO")} km</div>
                </div>
              </div>
            );
          })}
          {vs.length === 0 && <div className="p-6 text-sm text-muted-foreground">Niciun vehicul.</div>}
        </div>
      </CardContent></Card>
    </div>
  );
}

function AddVehicle() {
  const { session } = useAuth();
  const data = useData();
  const [open, setOpen] = useState(false);
  const [f, setF] = useState({ clientId: data.clients[0]?.id ?? "", marca: "", model: "", an: new Date().getFullYear(), vin: "", numarInmatriculare: "", km: 0 });
  const save = () => {
    if (!f.clientId || !f.marca || !f.model || !f.numarInmatriculare) return toast.error("Completați câmpurile obligatorii.");
    update((d) => d.vehicles.push({ id: uid("v"), ...f }));
    audit({ autor: session!.name, rol: "admin", actiune: "Vehicul creat", entitate: `${f.marca} ${f.model} ${f.numarInmatriculare}` });
    setOpen(false); toast.success("Vehicul adăugat.");
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button>Adaugă vehicul</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Vehicul nou</DialogTitle></DialogHeader>
        <div className="grid grid-cols-2 gap-2">
          <div className="col-span-2"><Label>Client</Label>
            <Select value={f.clientId} onValueChange={(v) => setF({ ...f, clientId: v })}>
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{data.clients.map((c) => <SelectItem key={c.id} value={c.id}>{c.prenume} {c.nume}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div><Label>Marcă</Label><Input value={f.marca} onChange={(e) => setF({ ...f, marca: e.target.value })} /></div>
          <div><Label>Model</Label><Input value={f.model} onChange={(e) => setF({ ...f, model: e.target.value })} /></div>
          <div><Label>An</Label><Input type="number" value={f.an} onChange={(e) => setF({ ...f, an: Number(e.target.value) })} /></div>
          <div><Label>Km</Label><Input type="number" value={f.km} onChange={(e) => setF({ ...f, km: Number(e.target.value) })} /></div>
          <div><Label>Nr. înmatriculare</Label><Input value={f.numarInmatriculare} onChange={(e) => setF({ ...f, numarInmatriculare: e.target.value })} /></div>
          <div><Label>VIN</Label><Input value={f.vin} onChange={(e) => setF({ ...f, vin: e.target.value })} /></div>
        </div>
        <DialogFooter><Button onClick={save}>Salvează</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}