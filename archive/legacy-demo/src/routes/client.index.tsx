import { createFileRoute, Link } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { useData } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { StatusBadge } from "@/components/status-badge";
import { deadlineStatus, fmtDate } from "@/lib/format";
import { Button } from "@/components/ui/button";

export const Route = createFileRoute("/client/")({
  component: ClientHome,
});

function ClientHome() {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const deadlines = data.deadlines
    .filter((d) => d.clientId === session?.clientId)
    .sort((a, b) => a.expiraLa.localeCompare(b.expiraLa));
  const urgente = deadlines.filter((d) => deadlineStatus(d.expiraLa).tone !== "ok");
  const mesajeNoi = data.messages.filter((m) => m.clientId === session?.clientId && m.autor === "admin" && !m.citit).length;

  return (
    <div>
      <PageHeader title={`Bine ați venit, ${session?.name?.split(" ")[0] ?? "client"}`} description="Sumar rapid al mașinilor și scadențelor dvs." />

      <div className="grid gap-4 md:grid-cols-4">
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Vehicule</CardTitle></CardHeader><CardContent className="text-2xl font-bold">{vehicles.length}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Scadențe urgente</CardTitle></CardHeader><CardContent className="text-2xl font-bold text-primary">{urgente.length}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Mesaje necitite</CardTitle></CardHeader><CardContent className="text-2xl font-bold">{mesajeNoi}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">Taxe neplătite</CardTitle></CardHeader><CardContent className="text-2xl font-bold">
          {data.taxes.filter((t) => t.clientId === session?.clientId && t.status === "neplatit").length}
        </CardContent></Card>
      </div>

      <div className="mt-8 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle>Următoarele scadențe</CardTitle></CardHeader>
          <CardContent>
            {deadlines.length === 0 ? (
              <p className="text-sm text-muted-foreground">Nu există scadențe înregistrate.</p>
            ) : (
              <ul className="divide-y">
                {deadlines.slice(0, 6).map((d) => {
                  const v = vehicles.find((x) => x.id === d.vehicleId);
                  const s = deadlineStatus(d.expiraLa);
                  return (
                    <li key={d.id} className="py-3 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                      <div className="min-w-0">
                        <div className="font-medium">{d.tip} — {v?.marca} {v?.model}</div>
                        <div className="text-xs text-muted-foreground truncate">{v?.numarInmatriculare} · Expiră la {fmtDate(d.expiraLa)}</div>
                      </div>
                      <StatusBadge tone={s.tone}>{s.label}</StatusBadge>
                    </li>
                  );
                })}
              </ul>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Acțiuni rapide</CardTitle></CardHeader>
          <CardContent className="grid gap-2 sm:grid-cols-2">
            <Button asChild variant="secondary"><Link to="/client/oferta">Cere ofertă reparație</Link></Button>
            <Button asChild variant="secondary"><Link to="/client/asistenta-rutiera">Solicită asistență rutieră</Link></Button>
            <Button asChild variant="secondary"><Link to="/client/mobilitate">Solicită mobilitate</Link></Button>
            <Button asChild variant="secondary"><Link to="/client/dosar-dauna">Deschide dosar daună</Link></Button>
            <Button asChild variant="secondary"><Link to="/client/mesaje">Trimite mesaj</Link></Button>
            <Button asChild variant="secondary"><Link to="/client/istoric">Vezi istoric service</Link></Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}