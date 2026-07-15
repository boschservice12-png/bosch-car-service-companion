import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { useAuth } from "@/lib/auth";
import { useNavigate } from "@tanstack/react-router";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useData, resetDemoData } from "@/lib/db";
import { toast } from "sonner";
import { Car, ShieldCheck, Users, Wrench } from "lucide-react";

export const Route = createFileRoute("/")({
  component: Index,
});

function Index() {
  const { session, login, ready } = useAuth();
  const navigate = useNavigate();
  const data = useData();
  const [email, setEmail] = useState("andrei.popescu@example.ro");
  const [pass, setPass] = useState("demo");

  useEffect(() => {
    if (!ready) return;
    if (session?.role === "client") navigate({ to: "/client" });
    if (session?.role === "admin") navigate({ to: "/admin" });
  }, [ready, session, navigate]);

  const loginAs = (role: "client" | "admin", clientId?: string) => {
    if (role === "client") {
      const c = data.clients.find((x) => x.id === clientId) ?? data.clients[0];
      login({ role, clientId: c.id, name: `${c.prenume} ${c.nume}` });
      toast.success(`Autentificat: ${c.prenume} ${c.nume}`);
      navigate({ to: "/client" });
    } else {
      login({ role: "admin", name: "Administrator Service" });
      toast.success("Autentificat ca administrator");
      navigate({ to: "/admin" });
    }
  };

  const handleFormLogin = (e: React.FormEvent) => {
    e.preventDefault();
    const c = data.clients.find((x) => x.email.toLowerCase() === email.trim().toLowerCase());
    if (!c) {
      toast.error("Client inexistent. Folosiți unul dintre emailurile demo.");
      return;
    }
    if (!pass) {
      toast.error("Introduceți parola.");
      return;
    }
    loginAs("client", c.id);
  };

  return (
    <div className="min-h-screen bg-background">
      <header className="border-b bg-card">
        <div className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="inline-block h-9 w-9 rounded bg-primary" aria-hidden />
            <div>
              <div className="font-bold tracking-tight">Auto Service</div>
              <div className="text-xs text-muted-foreground">Portal client & administrare</div>
            </div>
          </div>
          <Button variant="ghost" size="sm" onClick={() => { resetDemoData(); toast.success("Datele demo au fost resetate"); }}>
            Resetează datele demo
          </Button>
        </div>
      </header>

      <section className="max-w-6xl mx-auto px-4 py-12 grid md:grid-cols-2 gap-10 items-start">
        <div>
          <h1 className="text-4xl md:text-5xl font-bold tracking-tight leading-tight">
            Toată mașina dvs., într-un singur loc.
          </h1>
          <p className="mt-4 text-muted-foreground text-lg">
            Comunicați cu service-ul, urmăriți scadențele ITP, RCA, rovinietă și asistență, consultați istoricul complet al mașinii și solicitați ofertă, asistență rutieră sau mobilitate — totul digital.
          </p>
          <ul className="mt-6 space-y-3 text-sm">
            <li className="flex items-start gap-3"><ShieldCheck className="h-5 w-5 text-primary mt-0.5" /> <span>Scadențe monitorizate cu notificări în avans</span></li>
            <li className="flex items-start gap-3"><Wrench className="h-5 w-5 text-primary mt-0.5" /> <span>Istoric service publicat, corectat exclusiv prin înregistrări de corecție</span></li>
            <li className="flex items-start gap-3"><Car className="h-5 w-5 text-primary mt-0.5" /> <span>Asistență rutieră, mobilitate și dosar de daună gestionate integrat</span></li>
            <li className="flex items-start gap-3"><Users className="h-5 w-5 text-primary mt-0.5" /> <span>Comunicare directă cu echipa service-ului</span></li>
          </ul>
        </div>

        <div className="grid gap-4">
          <Card>
            <CardHeader>
              <CardTitle>Autentificare client</CardTitle>
              <CardDescription>Introduceți emailul și parola demo pentru a accesa portalul.</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={handleFormLogin} className="space-y-3">
                <div>
                  <Label htmlFor="email">Email</Label>
                  <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
                </div>
                <div>
                  <Label htmlFor="pass">Parolă</Label>
                  <Input id="pass" type="password" value={pass} onChange={(e) => setPass(e.target.value)} />
                </div>
                <Button type="submit" className="w-full">Intră în portalul client</Button>
                <p className="text-xs text-muted-foreground">
                  Conturi demo:{" "}
                  {data.clients.map((c) => (
                    <button key={c.id} type="button" className="underline mr-2" onClick={() => setEmail(c.email)}>
                      {c.email}
                    </button>
                  ))}
                </p>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Administrare service</CardTitle>
              <CardDescription>Acces demonstrativ pentru echipa service-ului.</CardDescription>
            </CardHeader>
            <CardContent>
              <Button variant="secondary" className="w-full" onClick={() => loginAs("admin")}>
                Intră ca administrator
              </Button>
            </CardContent>
          </Card>
        </div>
      </section>

      <footer className="border-t mt-8">
        <div className="max-w-6xl mx-auto px-4 py-6 text-xs text-muted-foreground">
          Aplicație demonstrativă. Datele sunt stocate local în browser pentru scopul acestei prezentări.
        </div>
      </footer>
    </div>
  );
}
