import { createFileRoute } from "@tanstack/react-router";
import { useState } from "react";
import { useData } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { StatusBadge } from "@/components/status-badge";
import { fmtDate, fmtRON, daysUntil } from "@/lib/format";

export const Route = createFileRoute("/admin/taxe")({ component: Page });

function Page() {
  const data = useData();
  const [filter, setFilter] = useState<"toate" | "neplatite" | "restante">("neplatite");
  const list = data.taxes
    .filter((t) => {
      if (filter === "toate") return true;
      if (filter === "restante") return t.status !== "platit" && daysUntil(t.scadenta) < 0;
      return t.status !== "platit";
    })
    .sort((a, b) => a.scadenta.localeCompare(b.scadenta));

  return (
    <div>
      <PageHeader title="Taxe și impozite" description="Toate taxele înregistrate ale clienților." />
      <div className="mb-3 max-w-xs">
        <Select value={filter} onValueChange={(v: "toate" | "neplatite" | "restante") => setFilter(v)}>
          <SelectTrigger><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="neplatite">Neplătite</SelectItem>
            <SelectItem value="restante">Restante</SelectItem>
            <SelectItem value="toate">Toate</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <Card><CardContent className="p-0 divide-y">
        {list.map((t) => {
          const c = data.clients.find((x) => x.id === t.clientId);
          const v = data.vehicles.find((x) => x.id === t.vehicleId);
          const days = daysUntil(t.scadenta);
          const tone = t.status === "platit" ? "ok" : days < 0 ? "danger" : days < 30 ? "warn" : "info";
          const lbl = t.status === "platit" ? `Plătit ${fmtDate(t.platitLa)}` : days < 0 ? `Restant ${Math.abs(days)} zile` : `Scadență în ${days} zile`;
          return (
            <div key={t.id} className="p-4 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
              <div className="min-w-0">
                <div className="font-medium truncate">{t.tip} {t.an} — {c?.prenume} {c?.nume}</div>
                <div className="text-xs text-muted-foreground truncate">{v?.marca} {v?.model} · {v?.numarInmatriculare} · Scadență {fmtDate(t.scadenta)} · {fmtRON(t.suma)}</div>
              </div>
              <StatusBadge tone={tone}>{lbl}</StatusBadge>
            </div>
          );
        })}
        {list.length === 0 && <div className="p-6 text-sm text-muted-foreground">Nicio taxă.</div>}
      </CardContent></Card>
    </div>
  );
}