import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData, update, audit } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { StatusBadge } from "@/components/status-badge";
import { fmtDate, fmtDateTime } from "@/lib/format";
import { useAuth } from "@/lib/auth";
import type { MobilityStatus } from "@/lib/types";
import { toast } from "sonner";

export const Route = createFileRoute("/admin/mobilitate")({ component: Page });

const TONE = { nou: "info", aprobat: "ok", respins: "danger", finalizat: "neutral" } as const;
const LABEL = { nou: "Nou", aprobat: "Aprobat", respins: "Respins", finalizat: "Finalizat" };

function Page() {
  const data = useData();
  const list = [...data.mobility].sort((a, b) => b.creatLa.localeCompare(a.creatLa));
  return (
    <div>
      <PageHeader title="Solicitări mobilitate" description="Cereri de mașină la schimb pentru clienți." />
      <Card><CardContent className="p-0 divide-y">
        {list.map((m) => {
          const c = data.clients.find((x) => x.id === m.clientId);
          return (
            <div key={m.id} className="p-4 space-y-2">
              <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                <div className="min-w-0">
                  <div className="font-medium truncate">{c?.prenume} {c?.nume} — {m.tipMasina}</div>
                  <div className="text-xs text-muted-foreground truncate">{fmtDate(m.perioadaStart)} → {fmtDate(m.perioadaEnd)} · trimis {fmtDateTime(m.creatLa)}</div>
                </div>
                <StatusBadge tone={TONE[m.status]}>{LABEL[m.status]}</StatusBadge>
              </div>
              <p className="text-sm">{m.motiv}</p>
              {m.raspuns && <p className="text-sm bg-muted rounded p-2">{m.raspuns}</p>}
              <div className="flex gap-2 flex-wrap">
                <SetStatus id={m.id} current={m.status} />
                <Reply id={m.id} />
              </div>
            </div>
          );
        })}
        {list.length === 0 && <div className="p-6 text-sm text-muted-foreground">Nicio solicitare.</div>}
      </CardContent></Card>
    </div>
  );
}

function SetStatus({ id, current }: { id: string; current: MobilityStatus }) {
  const { session } = useAuth();
  return (
    <Select value={current} onValueChange={(v: MobilityStatus) => {
      update((d) => { const m = d.mobility.find((x) => x.id === id); if (m) m.status = v; });
      audit({ autor: session!.name, rol: "admin", actiune: "Status mobilitate schimbat", entitate: `Cerere ${id}`, detalii: v });
    }}>
      <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
      <SelectContent>{(Object.keys(LABEL) as MobilityStatus[]).map((k) => <SelectItem key={k} value={k}>{LABEL[k]}</SelectItem>)}</SelectContent>
    </Select>
  );
}

function Reply({ id }: { id: string }) {
  const { session } = useAuth();
  const [open, setOpen] = useState(false);
  const [txt, setTxt] = useState("");
  const save = () => {
    if (!txt.trim()) return toast.error("Scrieți un răspuns.");
    update((d) => { const m = d.mobility.find((x) => x.id === id); if (m) m.raspuns = txt.trim(); });
    audit({ autor: session!.name, rol: "admin", actiune: "Răspuns mobilitate", entitate: `Cerere ${id}` });
    setOpen(false); toast.success("Răspuns trimis.");
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline">Răspunde</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>Răspuns către client</DialogTitle></DialogHeader>
        <Label>Mesaj</Label><Textarea rows={4} value={txt} onChange={(e) => setTxt(e.target.value)} />
        <DialogFooter><Button onClick={save}>Trimite</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}