import { createFileRoute } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader, EmptyState } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatusBadge } from "@/components/status-badge";
import { fmtDate, fmtRON, daysUntil } from "@/lib/format";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { useState } from "react";
import { toast } from "sonner";

export const Route = createFileRoute("/client/taxe")({ component: Page });

function Page() {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const taxe = data.taxes.filter((t) => t.clientId === session?.clientId).sort((a, b) => a.scadenta.localeCompare(b.scadenta));

  return (
    <div>
      <PageHeader title="Taxe și impozite auto" description="Urmăriți taxele anuale și marcați plățile efectuate." action={<AddTaxDialog />} />
      {taxe.length === 0 ? (
        <EmptyState title="Fără taxe înregistrate" description="Adăugați prima înregistrare pentru a urmări scadențele." />
      ) : (
        <Card>
          <CardHeader><CardTitle>Toate taxele</CardTitle></CardHeader>
          <CardContent className="p-0">
            <div className="divide-y">
              {taxe.map((t) => {
                const v = vehicles.find((x) => x.id === t.vehicleId);
                const days = daysUntil(t.scadenta);
                const tone = t.status === "platit" ? "ok" : days < 0 ? "danger" : days < 30 ? "warn" : "info";
                const lbl = t.status === "platit" ? `Plătit ${fmtDate(t.platitLa)}` : days < 0 ? `Restant ${Math.abs(days)} zile` : `Scadență în ${days} zile`;
                return (
                  <div key={t.id} className="p-4 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                    <div className="min-w-0">
                      <div className="font-medium truncate">{t.tip} {t.an} — {v?.marca} {v?.model}</div>
                      <div className="text-xs text-muted-foreground truncate">{v?.numarInmatriculare} · Scadență: {fmtDate(t.scadenta)} · {fmtRON(t.suma)}</div>
                    </div>
                    <div className="flex items-center gap-2">
                      <StatusBadge tone={tone}>{lbl}</StatusBadge>
                      {t.status !== "platit" && (
                        <Button size="sm" variant="outline" onClick={() => {
                          update((d) => { const x = d.taxes.find((y) => y.id === t.id); if (x) { x.status = "platit"; x.platitLa = new Date().toISOString().slice(0, 10); } });
                          audit({ autor: session!.name, rol: "client", actiune: "Taxă marcată plătită", entitate: `Taxă ${t.id}` });
                          toast.success("Marcat ca plătit.");
                        }}>Marchează plătit</Button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}

function AddTaxDialog() {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const [open, setOpen] = useState(false);
  const [vid, setVid] = useState(vehicles[0]?.id ?? "");
  const [an, setAn] = useState(new Date().getFullYear());
  const [tip, setTip] = useState("Impozit auto");
  const [suma, setSuma] = useState(0);
  const [scad, setScad] = useState("");

  const salveaza = () => {
    if (!vid || !tip || !scad || suma <= 0) return toast.error("Completați toate câmpurile.");
    update((d) => d.taxes.push({ id: uid("t"), clientId: session!.clientId!, vehicleId: vid, an, tip, suma, scadenta: scad, status: "neplatit" }));
    audit({ autor: session!.name, rol: "client", actiune: "Taxă adăugată", entitate: `Vehicul ${vid}`, detalii: `${tip} ${an} - ${suma} RON` });
    setOpen(false); toast.success("Taxă adăugată.");
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button>Adaugă taxă</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Taxă nouă</DialogTitle></DialogHeader>
        <div className="space-y-3">
          <div><Label>Mașină</Label>
            <Select value={vid} onValueChange={setVid}><SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>{vehicles.map((v) => <SelectItem key={v.id} value={v.id}>{v.marca} {v.model} — {v.numarInmatriculare}</SelectItem>)}</SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div><Label>Tip</Label><Input value={tip} onChange={(e) => setTip(e.target.value)} /></div>
            <div><Label>An</Label><Input type="number" value={an} onChange={(e) => setAn(Number(e.target.value))} /></div>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <div><Label>Sumă (RON)</Label><Input type="number" value={suma} onChange={(e) => setSuma(Number(e.target.value))} /></div>
            <div><Label>Scadență</Label><Input type="date" value={scad} onChange={(e) => setScad(e.target.value)} /></div>
          </div>
        </div>
        <DialogFooter><Button onClick={salveaza}>Salvează</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}