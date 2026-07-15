import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData, update, audit } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { StatusBadge } from "@/components/status-badge";
import { fmtDateTime, fmtRON } from "@/lib/format";
import { useAuth } from "@/lib/auth";
import type { OfferStatus } from "@/lib/types";
import { toast } from "sonner";

export const Route = createFileRoute("/admin/oferte")({ component: Page });

const TONE = { nou: "info", in_analiza: "warn", oferta_trimisa: "info", acceptata: "ok", refuzata: "danger" } as const;
const LABEL = { nou: "Nou", in_analiza: "În analiză", oferta_trimisa: "Ofertă trimisă", acceptata: "Acceptată", refuzata: "Refuzată" };

function Page() {
  const data = useData();
  const [status, setStatus] = useState<"toate" | OfferStatus>("toate");
  const list = data.offers.filter((o) => status === "toate" || o.status === status).sort((a, b) => b.creatLa.localeCompare(a.creatLa));
  return (
    <div>
      <PageHeader title="Cereri de ofertă" description="Gestionați și trimiteți estimările către clienți." />
      <div className="mb-3 max-w-xs">
        <Select value={status} onValueChange={(v: "toate" | OfferStatus) => setStatus(v)}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="toate">Toate</SelectItem>
            {(Object.keys(LABEL) as OfferStatus[]).map((k) => <SelectItem key={k} value={k}>{LABEL[k]}</SelectItem>)}
          </SelectContent>
        </Select>
      </div>
      <Card><CardContent className="p-0 divide-y">
        {list.map((o) => {
          const c = data.clients.find((x) => x.id === o.clientId);
          const v = data.vehicles.find((x) => x.id === o.vehicleId);
          return (
            <div key={o.id} className="p-4 space-y-2">
              <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                <div className="min-w-0">
                  <div className="font-medium truncate">{c?.prenume} {c?.nume} — {v?.marca} {v?.model} ({v?.numarInmatriculare})</div>
                  <div className="text-xs text-muted-foreground">Urgență: {o.urgenta} · {fmtDateTime(o.creatLa)}</div>
                </div>
                <StatusBadge tone={TONE[o.status]}>{LABEL[o.status]}</StatusBadge>
              </div>
              <p className="text-sm">{o.descriere}</p>
              {o.ofertaText && <div className="rounded bg-muted p-2 text-sm"><div className="font-medium mb-1">Ofertă · {fmtRON(o.ofertaSuma)}</div>{o.ofertaText}</div>}
              <div className="flex flex-wrap gap-2">
                <SetStatus id={o.id} current={o.status} />
                <SendOffer id={o.id} />
              </div>
            </div>
          );
        })}
        {list.length === 0 && <div className="p-6 text-sm text-muted-foreground">Nicio cerere.</div>}
      </CardContent></Card>
    </div>
  );
}

function SetStatus({ id, current }: { id: string; current: OfferStatus }) {
  const { session } = useAuth();
  return (
    <Select value={current} onValueChange={(v: OfferStatus) => {
      update((d) => { const o = d.offers.find((x) => x.id === id); if (o) o.status = v; });
      audit({ autor: session!.name, rol: "admin", actiune: "Status ofertă schimbat", entitate: `Ofertă ${id}`, detalii: v });
      toast.success("Status actualizat.");
    }}>
      <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
      <SelectContent>{(Object.keys(LABEL) as OfferStatus[]).map((k) => <SelectItem key={k} value={k}>{LABEL[k]}</SelectItem>)}</SelectContent>
    </Select>
  );
}

function SendOffer({ id }: { id: string }) {
  const { session } = useAuth();
  const [open, setOpen] = useState(false);
  const [text, setText] = useState("");
  const [suma, setSuma] = useState(0);
  const save = () => {
    if (!text.trim() || suma <= 0) return toast.error("Completați oferta.");
    update((d) => { const o = d.offers.find((x) => x.id === id); if (o) { o.ofertaText = text.trim(); o.ofertaSuma = suma; o.status = "oferta_trimisa"; } });
    audit({ autor: session!.name, rol: "admin", actiune: "Ofertă trimisă", entitate: `Ofertă ${id}` });
    setOpen(false); toast.success("Ofertă trimisă clientului.");
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm">Trimite ofertă</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Redactare ofertă</DialogTitle></DialogHeader>
        <div className="space-y-2">
          <div><Label>Text ofertă</Label><Textarea rows={5} value={text} onChange={(e) => setText(e.target.value)} /></div>
          <div><Label>Sumă totală (RON)</Label><Input type="number" value={suma} onChange={(e) => setSuma(Number(e.target.value))} /></div>
        </div>
        <DialogFooter><Button onClick={save}>Trimite</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}