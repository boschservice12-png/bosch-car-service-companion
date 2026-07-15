import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { useAuth } from "@/lib/auth";
import { toast } from "sonner";

export const Route = createFileRoute("/admin/clienti")({ component: Page });

function Page() {
  const data = useData();
  const [q, setQ] = useState("");
  const clients = data.clients.filter((c) => `${c.nume} ${c.prenume} ${c.email} ${c.telefon}`.toLowerCase().includes(q.toLowerCase()));

  return (
    <div>
      <PageHeader title="Clienți" description="Toți clienții persoane fizice." action={<AddClient />} />
      <div className="mb-3 max-w-sm">
        <Input placeholder="Caută după nume, email, telefon…" value={q} onChange={(e) => setQ(e.target.value)} />
      </div>
      <Card>
        <CardContent className="p-0">
          <div className="divide-y">
            {clients.map((c) => {
              const nv = data.vehicles.filter((v) => v.clientId === c.id).length;
              return (
                <div key={c.id} className="p-4 grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                  <div className="min-w-0">
                    <div className="font-medium truncate">{c.prenume} {c.nume}</div>
                    <div className="text-xs text-muted-foreground truncate">{c.email} · {c.telefon} · {c.adresa}</div>
                  </div>
                  <div className="text-xs text-muted-foreground shrink-0">{nv} vehicul(e)</div>
                </div>
              );
            })}
            {clients.length === 0 && <div className="p-6 text-sm text-muted-foreground">Niciun client găsit.</div>}
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function AddClient() {
  const { session } = useAuth();
  const [open, setOpen] = useState(false);
  const [f, setF] = useState({ nume: "", prenume: "", telefon: "", email: "", adresa: "", cnp: "" });
  const save = () => {
    if (!f.nume || !f.prenume || !f.email) return toast.error("Nume, prenume și email obligatorii.");
    update((d) => d.clients.push({ id: uid("c"), ...f, creatLa: new Date().toISOString().slice(0, 10) }));
    audit({ autor: session!.name, rol: "admin", actiune: "Client creat", entitate: `${f.prenume} ${f.nume}` });
    setOpen(false); toast.success("Client adăugat.");
    setF({ nume: "", prenume: "", telefon: "", email: "", adresa: "", cnp: "" });
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button>Adaugă client</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Client nou</DialogTitle></DialogHeader>
        <div className="grid grid-cols-2 gap-2">
          <div><Label>Nume</Label><Input value={f.nume} onChange={(e) => setF({ ...f, nume: e.target.value })} /></div>
          <div><Label>Prenume</Label><Input value={f.prenume} onChange={(e) => setF({ ...f, prenume: e.target.value })} /></div>
          <div className="col-span-2"><Label>Email</Label><Input type="email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} /></div>
          <div><Label>Telefon</Label><Input value={f.telefon} onChange={(e) => setF({ ...f, telefon: e.target.value })} /></div>
          <div><Label>CNP</Label><Input value={f.cnp} onChange={(e) => setF({ ...f, cnp: e.target.value })} /></div>
          <div className="col-span-2"><Label>Adresă</Label><Input value={f.adresa} onChange={(e) => setF({ ...f, adresa: e.target.value })} /></div>
        </div>
        <DialogFooter><Button onClick={save}>Salvează</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}