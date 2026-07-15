import { createFileRoute, Link } from "@tanstack/react-router";
import { useData } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "@/components/status-badge";
import { deadlineStatus, fmtDate } from "@/lib/format";

export const Route = createFileRoute("/admin/")({ component: Dashboard });

function Dashboard() {
  const data = useData();
  const urgente = data.deadlines.filter((d) => deadlineStatus(d.expiraLa).tone !== "ok");
  const oferteNoi = data.offers.filter((o) => o.status === "nou" || o.status === "in_analiza").length;
  const asistenteNoi = data.assistance.filter((a) => a.status === "nou" || a.status === "trimis_echipa" || a.status === "in_curs").length;
  const mobilitateNoi = data.mobility.filter((m) => m.status === "nou").length;
  const dosareActive = data.damages.filter((d) => d.status !== "inchis").length;
  const mesajeNoi = data.messages.filter((m) => m.autor === "client" && !m.citit).length;

  const kpi = [
    { label: "Clienți", value: data.clients.length, href: "/admin/clienti" },
    { label: "Vehicule", value: data.vehicles.length, href: "/admin/vehicule" },
    { label: "Scadențe urgente", value: urgente.length, href: "/admin/scadente" },
    { label: "Cereri ofertă active", value: oferteNoi, href: "/admin/oferte" },
    { label: "Solicitări asistență", value: asistenteNoi, href: "/admin/asistenta" },
    { label: "Solicitări mobilitate", value: mobilitateNoi, href: "/admin/mobilitate" },
    { label: "Dosare daună active", value: dosareActive, href: "/admin/dosare" },
    { label: "Mesaje necitite", value: mesajeNoi, href: "/admin/mesaje" },
  ];

  return (
    <div>
      <PageHeader title="Dashboard" description="Sumar operațional al service-ului." />
      <div className="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
        {kpi.map((k) => (
          <Link key={k.label} to={k.href}>
            <Card className="hover:border-primary transition-colors">
              <CardHeader className="pb-1"><CardTitle className="text-sm text-muted-foreground font-normal">{k.label}</CardTitle></CardHeader>
              <CardContent className="text-3xl font-bold">{k.value}</CardContent>
            </Card>
          </Link>
        ))}
      </div>

      <div className="mt-8">
        <Card>
          <CardHeader><CardTitle>Scadențe critice</CardTitle></CardHeader>
          <CardContent className="p-0">
            <div className="divide-y">
              {urgente.slice(0, 8).map((d) => {
                const v = data.vehicles.find((x) => x.id === d.vehicleId);
                const c = data.clients.find((x) => x.id === d.clientId);
                const s = deadlineStatus(d.expiraLa);
                return (
                  <div key={d.id} className="p-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                    <div className="min-w-0">
                      <div className="font-medium truncate">{c?.prenume} {c?.nume} — {v?.marca} {v?.model} ({v?.numarInmatriculare})</div>
                      <div className="text-xs text-muted-foreground">{d.tip} · expiră {fmtDate(d.expiraLa)}</div>
                    </div>
                    <StatusBadge tone={s.tone}>{s.label}</StatusBadge>
                  </div>
                );
              })}
              {urgente.length === 0 && <div className="p-6 text-sm text-muted-foreground">Nicio scadență critică.</div>}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}