import { createFileRoute } from "@tanstack/react-router";
import { useData } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent } from "@/components/ui/card";
import { StatusBadge } from "@/components/status-badge";
import { fmtDateTime } from "@/lib/format";

export const Route = createFileRoute("/admin/audit")({ component: Page });

function Page() {
  const data = useData();
  return (
    <div>
      <PageHeader title="Audit" description="Jurnal cronologic al modificărilor din aplicație." />
      <Card><CardContent className="p-0 divide-y">
        {data.audit.map((a) => (
          <div key={a.id} className="p-3 grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
            <div className="min-w-0">
              <div className="font-medium truncate">{a.actiune}</div>
              <div className="text-xs text-muted-foreground truncate">{a.entitate}{a.detalii ? ` · ${a.detalii}` : ""} · {a.autor} · {fmtDateTime(a.timestamp)}</div>
            </div>
            <StatusBadge tone={a.rol === "admin" ? "info" : "neutral"}>{a.rol}</StatusBadge>
          </div>
        ))}
        {data.audit.length === 0 && <div className="p-6 text-sm text-muted-foreground">Nicio activitate.</div>}
      </CardContent></Card>
    </div>
  );
}