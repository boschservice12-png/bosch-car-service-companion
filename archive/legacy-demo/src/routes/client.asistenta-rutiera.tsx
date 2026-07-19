import { createFileRoute } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatusBadge } from "@/components/status-badge";
import { fmtDateTime } from "@/lib/format";
import { useState } from "react";
import { toast } from "sonner";

export const Route = createFileRoute("/client/asistenta-rutiera")({ component: Page });

const TONE = { nou: "info", trimis_echipa: "warn", in_curs: "warn", finalizat: "ok", anulat: "neutral" } as const;
const LABEL = { nou: "Nou", trimis_echipa: "Echipă trimisă", in_curs: "În curs", finalizat: "Finalizat", anulat: "Anulat" };

function Page() {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const [vid, setVid] = useState(vehicles[0]?.id ?? "");
  const [loc, setLoc] = useState("");
  const [desc, setDesc] = useState("");
  const [tel, setTel] = useState("");
  const cereri = data.assistance.filter((a) => a.clientId === session?.clientId).sort((a, b) => b.creatLa.localeCompare(a.creatLa));

  const trimite = () => {
    if (!vid || !loc.trim() || !desc.trim() || !tel.trim()) return toast.error("Completați toate câmpurile.");
    if (!/^[0-9 +().-]{7,}$/.test(tel)) return toast.error("Telefon invalid.");
    update((d) => {
      d.assistance.push({ id: uid("as"), clientId: session!.clientId!, vehicleId: vid, locatie: loc.trim(), descriere: desc.trim(), telefonContact: tel.trim(), status: "nou", creatLa: new Date().toISOString() });
    });
    audit({ autor: session!.name, rol: "client", actiune: "Solicitare asistență rutieră", entitate: `Vehicul ${vid}` });
    setLoc(""); setDesc(""); setTel("");
    toast.success("Solicitarea a fost înregistrată. Vă vom contacta.");
  };

  return (
    <div>
      <PageHeader title="Solicită asistență rutieră" description="Rămas în pană? Trimiteți-ne detaliile — vă contactăm cât mai repede." />
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle>Cerere nouă</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <div><Label>Mașină</Label>
              <Select value={vid} onValueChange={setVid}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>{vehicles.map((v) => <SelectItem key={v.id} value={v.id}>{v.marca} {v.model} — {v.numarInmatriculare}</SelectItem>)}</SelectContent>
              </Select>
            </div>
            <div><Label>Locație</Label><Input value={loc} onChange={(e) => setLoc(e.target.value)} placeholder="Ex: A1 km 105, sens București → Pitești" /></div>
            <div><Label>Descrierea situației</Label><Textarea rows={4} value={desc} onChange={(e) => setDesc(e.target.value)} /></div>
            <div><Label>Telefon contact</Label><Input value={tel} onChange={(e) => setTel(e.target.value)} placeholder="07xx xxx xxx" /></div>
            <Button onClick={trimite}>Trimite solicitarea</Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Solicitările mele</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {cereri.length === 0 && <p className="text-sm text-muted-foreground">Nicio solicitare.</p>}
            {cereri.map((a) => (
              <div key={a.id} className="border rounded-md p-3">
                <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                  <div className="min-w-0">
                    <div className="font-medium truncate">{a.locatie}</div>
                    <div className="text-xs text-muted-foreground">{fmtDateTime(a.creatLa)}</div>
                  </div>
                  <StatusBadge tone={TONE[a.status]}>{LABEL[a.status]}</StatusBadge>
                </div>
                <p className="text-sm mt-2">{a.descriere}</p>
                {a.raspuns && <p className="text-sm mt-2 bg-muted rounded p-2"><span className="font-medium">Service: </span>{a.raspuns}</p>}
              </div>
            ))}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

export { LABEL as ASSISTANCE_LABEL, TONE as ASSISTANCE_TONE };