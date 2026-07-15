import { createFileRoute } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { StatusBadge } from "@/components/status-badge";
import { fmtDate, fmtDateTime } from "@/lib/format";
import { useState } from "react";
import { toast } from "sonner";

export const Route = createFileRoute("/client/mobilitate")({ component: Page });

const TONE = { nou: "info", aprobat: "ok", respins: "danger", finalizat: "neutral" } as const;
const LABEL = { nou: "Nou", aprobat: "Aprobat", respins: "Respins", finalizat: "Finalizat" };

function Page() {
  const { session } = useAuth();
  const data = useData();
  const [start, setStart] = useState("");
  const [end, setEnd] = useState("");
  const [tip, setTip] = useState("Autoturism clasa B");
  const [motiv, setMotiv] = useState("");
  const cereri = data.mobility.filter((m) => m.clientId === session?.clientId).sort((a, b) => b.creatLa.localeCompare(a.creatLa));

  const trimite = () => {
    if (!start || !end || !motiv.trim()) return toast.error("Completați toate câmpurile.");
    if (end < start) return toast.error("Data finală nu poate fi înainte de cea de început.");
    update((d) => {
      d.mobility.push({ id: uid("mb"), clientId: session!.clientId!, perioadaStart: start, perioadaEnd: end, tipMasina: tip, motiv: motiv.trim(), status: "nou", creatLa: new Date().toISOString() });
    });
    audit({ autor: session!.name, rol: "client", actiune: "Solicitare mobilitate", entitate: "Mobilitate" });
    setMotiv(""); setStart(""); setEnd("");
    toast.success("Solicitarea a fost înregistrată.");
  };

  return (
    <div>
      <PageHeader title="Solicită mobilitate" description="Aveți nevoie de o mașină la schimb pe perioada reparației?" />
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle>Cerere nouă</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <div className="grid grid-cols-2 gap-2">
              <div><Label>De la</Label><Input type="date" value={start} onChange={(e) => setStart(e.target.value)} /></div>
              <div><Label>Până la</Label><Input type="date" value={end} onChange={(e) => setEnd(e.target.value)} /></div>
            </div>
            <div><Label>Tip mașină dorită</Label><Input value={tip} onChange={(e) => setTip(e.target.value)} /></div>
            <div><Label>Motiv</Label><Textarea rows={3} value={motiv} onChange={(e) => setMotiv(e.target.value)} /></div>
            <Button onClick={trimite}>Trimite solicitarea</Button>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle>Solicitările mele</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {cereri.length === 0 && <p className="text-sm text-muted-foreground">Nicio solicitare.</p>}
            {cereri.map((m) => (
              <div key={m.id} className="border rounded-md p-3">
                <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                  <div className="min-w-0">
                    <div className="font-medium truncate">{m.tipMasina}</div>
                    <div className="text-xs text-muted-foreground">{fmtDate(m.perioadaStart)} → {fmtDate(m.perioadaEnd)} · trimis {fmtDateTime(m.creatLa)}</div>
                  </div>
                  <StatusBadge tone={TONE[m.status]}>{LABEL[m.status]}</StatusBadge>
                </div>
                <p className="text-sm mt-2">{m.motiv}</p>
                {m.raspuns && <p className="text-sm mt-2 bg-muted rounded p-2"><span className="font-medium">Service: </span>{m.raspuns}</p>}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export { LABEL as MOBILITY_LABEL, TONE as MOBILITY_TONE };