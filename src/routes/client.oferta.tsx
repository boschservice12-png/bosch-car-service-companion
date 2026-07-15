import { createFileRoute } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatusBadge } from "@/components/status-badge";
import { fmtDateTime, fmtRON } from "@/lib/format";
import { useState } from "react";
import { toast } from "sonner";

export const Route = createFileRoute("/client/oferta")({ component: Oferta });

const STATUS_TONE = { nou: "info", in_analiza: "warn", oferta_trimisa: "info", acceptata: "ok", refuzata: "danger" } as const;
const STATUS_LABEL = { nou: "Nou", in_analiza: "În analiză", oferta_trimisa: "Ofertă trimisă", acceptata: "Acceptată", refuzata: "Refuzată" };

function Oferta() {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const [vid, setVid] = useState(vehicles[0]?.id ?? "");
  const [desc, setDesc] = useState("");
  const [urg, setUrg] = useState<"scazuta" | "medie" | "ridicata">("medie");
  const cereri = data.offers.filter((o) => o.clientId === session?.clientId).sort((a, b) => b.creatLa.localeCompare(a.creatLa));

  const trimite = () => {
    if (!vid) return toast.error("Selectați o mașină.");
    if (desc.trim().length < 10) return toast.error("Descrierea trebuie să aibă cel puțin 10 caractere.");
    update((d) => {
      d.offers.push({ id: uid("o"), clientId: session!.clientId!, vehicleId: vid, descriere: desc.trim(), urgenta: urg, poze: [], status: "nou", creatLa: new Date().toISOString() });
    });
    audit({ autor: session!.name, rol: "client", actiune: "Cerere ofertă creată", entitate: `Vehicul ${vid}` });
    setDesc("");
    toast.success("Cererea a fost trimisă.");
  };

  const raspunde = (id: string, accept: boolean) => {
    update((d) => {
      const o = d.offers.find((x) => x.id === id);
      if (o) o.status = accept ? "acceptata" : "refuzata";
    });
    audit({ autor: session!.name, rol: "client", actiune: accept ? "Ofertă acceptată" : "Ofertă refuzată", entitate: `Ofertă ${id}` });
    toast.success("Răspuns înregistrat.");
  };

  return (
    <div>
      <PageHeader title="Cere ofertă de reparație" description="Descrieți problema; service-ul revine cu o estimare." />
      <div className="grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle>Cerere nouă</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <div>
              <Label>Mașină</Label>
              <Select value={vid} onValueChange={setVid}>
                <SelectTrigger><SelectValue placeholder="Selectați o mașină" /></SelectTrigger>
                <SelectContent>{vehicles.map((v) => <SelectItem key={v.id} value={v.id}>{v.marca} {v.model} — {v.numarInmatriculare}</SelectItem>)}</SelectContent>
              </Select>
            </div>
            <div>
              <Label>Descrierea problemei</Label>
              <Textarea rows={5} value={desc} onChange={(e) => setDesc(e.target.value)} placeholder="Ex: zgomot metalic la frânare, apare intermitent…" />
            </div>
            <div>
              <Label>Urgență</Label>
              <Select value={urg} onValueChange={(v: "scazuta" | "medie" | "ridicata") => setUrg(v)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="scazuta">Scăzută</SelectItem>
                  <SelectItem value="medie">Medie</SelectItem>
                  <SelectItem value="ridicata">Ridicată</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <Button onClick={trimite}>Trimite cererea</Button>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Cererile mele</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            {cereri.length === 0 && <p className="text-sm text-muted-foreground">Nicio cerere înregistrată.</p>}
            {cereri.map((o) => {
              const v = data.vehicles.find((x) => x.id === o.vehicleId);
              return (
                <div key={o.id} className="border rounded-md p-3 space-y-2">
                  <div className="grid grid-cols-[minmax(0,1fr)_auto] gap-2 items-start">
                    <div className="min-w-0">
                      <div className="font-medium truncate">{v?.marca} {v?.model} — {v?.numarInmatriculare}</div>
                      <div className="text-xs text-muted-foreground">{fmtDateTime(o.creatLa)}</div>
                    </div>
                    <StatusBadge tone={STATUS_TONE[o.status]}>{STATUS_LABEL[o.status]}</StatusBadge>
                  </div>
                  <p className="text-sm">{o.descriere}</p>
                  {o.ofertaText && (
                    <div className="rounded bg-muted p-2 text-sm">
                      <div className="font-medium mb-1">Ofertă service · {fmtRON(o.ofertaSuma)}</div>
                      <p className="whitespace-pre-wrap">{o.ofertaText}</p>
                    </div>
                  )}
                  {o.status === "oferta_trimisa" && (
                    <div className="flex gap-2">
                      <Button size="sm" onClick={() => raspunde(o.id, true)}>Accept</Button>
                      <Button size="sm" variant="outline" onClick={() => raspunde(o.id, false)}>Refuz</Button>
                    </div>
                  )}
                </div>
              );
            })}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}