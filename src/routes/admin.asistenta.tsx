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
import { fmtDateTime } from "@/lib/format";
import { useAuth } from "@/lib/auth";
import type { AssistanceStatus } from "@/lib/types";
import { toast } from "sonner";

export const Route = createFileRoute("/admin/asistenta")({ component: Page });

const TONE = { nou: "info", trimis_echipa: "warn", in_curs: "warn", finalizat: "ok", anulat: "neutral" } as const;
const LABEL = { nou: "Nou", trimis_echipa: "Echipă trimisă", in_curs: "În curs", finalizat: "Finalizat", anulat: "Anulat" };

function Page() {
  const data = useData();
  const list = [...data.assistance].sort((a, b) => b.creatLa.localeCompare(a.creatLa));
  return (
    <div>
      <PageHeader title="Asistență rutieră" description="Solicitările de intervenție ale clienților." />
      <Card><CardContent className="p-0 divide-y">
        {list.map((a) => {
          const c = data.clients.find((x) => x.id === a.clientId);
          const v = data.vehicles.find((x) => x.id === a.vehicleId);
          return (
            <div key={a.id} className="p-4 space-y-2">
              <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                <div className="min-w-0">
                  <div className="font-medium truncate">{c?.prenume} {c?.nume} — {v?.marca} {v?.model}</div>
                  <div className="text-xs text-muted-foreground truncate">{a.locatie} · tel {a.telefonContact} · {fmtDateTime(a.creatLa)}</div>
                </div>
                <StatusBadge tone={TONE[a.status]}>{LABEL[a.status]}</StatusBadge>
              </div>
              <p className="text-sm">{a.descriere}</p>
              {a.raspuns && <p className="text-sm bg-muted rounded p-2">{a.raspuns}</p>}
              <div className="flex gap-2 flex-wrap">
                <SetStatus id={a.id} current={a.status} />
                <Reply id={a.id} />
              </div>
            </div>
          );
        })}
        {list.length === 0 && <div className="p-6 text-sm text-muted-foreground">Nicio solicitare.</div>}
      </CardContent></Card>
    </div>
  );
}

function SetStatus({ id, current }: { id: string; current: AssistanceStatus }) {
  const { session } = useAuth();
  return (
    <Select value={current} onValueChange={(v: AssistanceStatus) => {
      update((d) => { const a = d.assistance.find((x) => x.id === id); if (a) a.status = v; });
      audit({ autor: session!.name, rol: "admin", actiune: "Status asistență schimbat", entitate: `Cerere ${id}`, detalii: v });
    }}>
      <SelectTrigger className="w-44"><SelectValue /></SelectTrigger>
      <SelectContent>{(Object.keys(LABEL) as AssistanceStatus[]).map((k) => <SelectItem key={k} value={k}>{LABEL[k]}</SelectItem>)}</SelectContent>
    </Select>
  );
}

function Reply({ id }: { id: string }) {
  const { session } = useAuth();
  const [open, setOpen] = useState(false);
  const [txt, setTxt] = useState("");
  const save = () => {
    if (!txt.trim()) return toast.error("Scrieți un răspuns.");
    update((d) => { const a = d.assistance.find((x) => x.id === id); if (a) a.raspuns = txt.trim(); });
    audit({ autor: session!.name, rol: "admin", actiune: "Răspuns asistență", entitate: `Cerere ${id}` });
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