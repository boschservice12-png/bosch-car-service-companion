import { createFileRoute } from "@tanstack/react-router";
import { useAuth, useCurrentClient } from "@/lib/auth";
import { useData, update, audit, uid, fileToDataUrl } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useState } from "react";
import { toast } from "sonner";

export const Route = createFileRoute("/client/profil")({ component: Page });

function Page() {
  const { session } = useAuth();
  const client = useCurrentClient();
  const data = useData();
  const [form, setForm] = useState(client ?? { nume: "", prenume: "", telefon: "", email: "", adresa: "", cnp: "" });
  const docs = data.documents.filter((d) => d.clientId === session?.clientId);

  if (!client) return <p>Client inexistent.</p>;

  const save = () => {
    update((d) => {
      const c = d.clients.find((x) => x.id === client.id);
      if (c) Object.assign(c, form);
    });
    audit({ autor: session!.name, rol: "client", actiune: "Profil actualizat", entitate: `Client ${client.id}` });
    toast.success("Profil actualizat.");
  };

  const upload = async (files: FileList | null, tip: string) => {
    if (!files?.length) return;
    for (const f of Array.from(files)) {
      if (f.size > 2 * 1024 * 1024) { toast.error(`${f.name} > 2MB`); continue; }
      const url = await fileToDataUrl(f);
      update((d) => d.documents.push({ id: uid("doc"), clientId: session!.clientId!, nume: f.name, tip, url, incarcatLa: new Date().toISOString() }));
    }
    audit({ autor: session!.name, rol: "client", actiune: "Document încărcat", entitate: tip });
    toast.success("Documente încărcate.");
  };

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <div>
        <PageHeader title="Profil" description="Date de contact și documente personale." />
        <Card>
          <CardHeader><CardTitle>Date personale</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <div className="grid grid-cols-2 gap-2">
              <div><Label>Nume</Label><Input value={form.nume} onChange={(e) => setForm({ ...form, nume: e.target.value })} /></div>
              <div><Label>Prenume</Label><Input value={form.prenume} onChange={(e) => setForm({ ...form, prenume: e.target.value })} /></div>
            </div>
            <div><Label>Email</Label><Input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></div>
            <div><Label>Telefon</Label><Input value={form.telefon} onChange={(e) => setForm({ ...form, telefon: e.target.value })} /></div>
            <div><Label>Adresă</Label><Input value={form.adresa} onChange={(e) => setForm({ ...form, adresa: e.target.value })} /></div>
            <div><Label>CNP (opțional)</Label><Input value={form.cnp ?? ""} onChange={(e) => setForm({ ...form, cnp: e.target.value })} /></div>
            <Button onClick={save}>Salvează</Button>
          </CardContent>
        </Card>
      </div>
      <div>
        <PageHeader title="Documente" description="Talon, permis, poliță RCA, etc." />
        <Card>
          <CardHeader><CardTitle>Încarcă documente</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <div><Label>Talon / certificat de înmatriculare</Label><Input type="file" multiple onChange={(e) => upload(e.target.files, "Talon")} /></div>
            <div><Label>Permis conducere</Label><Input type="file" multiple onChange={(e) => upload(e.target.files, "Permis")} /></div>
            <div><Label>Alte documente</Label><Input type="file" multiple onChange={(e) => upload(e.target.files, "Alt document")} /></div>
            {docs.length > 0 && (
              <ul className="text-sm space-y-1 border-t pt-3">
                {docs.map((d) => <li key={d.id}><a className="underline" href={d.url} target="_blank" rel="noreferrer">{d.nume}</a> <span className="text-muted-foreground">· {d.tip}</span></li>)}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}