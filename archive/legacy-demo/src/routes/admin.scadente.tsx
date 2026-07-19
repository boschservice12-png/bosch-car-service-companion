import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatusBadge } from "@/components/status-badge";
import { deadlineStatus, fmtDate } from "@/lib/format";

export const Route = createFileRoute("/admin/scadente")({ component: Page });

function Page() {
  const data = useData();
  const [filter, setFilter] = useState<"toate" | "urgente" | "expirate">("urgente");
  const items = data.deadlines
    .filter((d) => {
      const s = deadlineStatus(d.expiraLa);
      if (filter === "toate") return true;
      if (filter === "urgente") return s.tone !== "ok";
      return s.tone === "danger";
    })
    .sort((a, b) => a.expiraLa.localeCompare(b.expiraLa));

  return (
    <div>
      <PageHeader title="Scadențe" description="ITP, RCA, rovinietă și asistență rutieră pentru toți clienții." />
      <div className="mb-3 max-w-xs">
        <Select value={filter} onValueChange={(v: "toate" | "urgente" | "expirate") => setFilter(v)}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="urgente">Urgente (≤30 zile sau expirate)</SelectItem>
            <SelectItem value="expirate">Doar expirate</SelectItem>
            <SelectItem value="toate">Toate</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <Card><CardContent className="p-0">
        <div className="divide-y">
          {items.map((d) => {
            const v = data.vehicles.find((x) => x.id === d.vehicleId);
            const c = data.clients.find((x) => x.id === d.clientId);
            const s = deadlineStatus(d.expiraLa);
            return (
              <div key={d.id} className="p-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                <div className="min-w-0">
                  <div className="font-medium truncate">{d.tip} — {c?.prenume} {c?.nume}</div>
                  <div className="text-xs text-muted-foreground truncate">{v?.marca} {v?.model} · {v?.numarInmatriculare} · expiră {fmtDate(d.expiraLa)}</div>
                </div>
                <StatusBadge tone={s.tone}>{s.label}</StatusBadge>
              </div>
            );
          })}
          {items.length === 0 && <div className="p-6 text-sm text-muted-foreground">Nicio scadență.</div>}
        </div>
      </CardContent></Card>
    </div>
  );
}