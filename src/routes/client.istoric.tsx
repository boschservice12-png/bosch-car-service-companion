import { createFileRoute } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { useData } from "@/lib/db";
import { PageHeader, EmptyState } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { fmtDate, fmtRON } from "@/lib/format";
import { useState } from "react";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatusBadge } from "@/components/status-badge";

export const Route = createFileRoute("/client/istoric")({ component: Istoric });

function Istoric() {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const [vid, setVid] = useState<string>(vehicles[0]?.id ?? "");
  const entries = data.serviceHistory
    .filter((s) => s.vehicleId === vid)
    .sort((a, b) => a.data.localeCompare(b.data));

  return (
    <div>
      <PageHeader title="Istoric service" description="Istoric complet începând cu prima intrare în service. Corecțiile sunt înregistrări separate — nu suprascriu istoricul." />
      <div className="mb-4 max-w-sm">
        <Select value={vid} onValueChange={setVid}>
          <SelectTrigger><SelectValue placeholder="Selectați o mașină" /></SelectTrigger>
          <SelectContent>{vehicles.map((v) => <SelectItem key={v.id} value={v.id}>{v.marca} {v.model} — {v.numarInmatriculare}</SelectItem>)}</SelectContent>
        </Select>
      </div>
      {entries.length === 0 ? (
        <EmptyState title="Fără intrări" description="Nu există înregistrări pentru această mașină." />
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
                  {e.cost != null && <span className="text-muted-foreground">Cost: <span className="font-medium text-foreground">{fmtRON(e.cost)}</span></span>}
                  {e.corectieDe && <StatusBadge tone="warn">Corecție</StatusBadge>}
                </div>
                {e.motivCorectie && <p className="text-xs text-muted-foreground">Motiv corecție: {e.motivCorectie}</p>}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}