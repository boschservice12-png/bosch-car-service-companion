import { useAuth } from "@/lib/auth";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader, EmptyState } from "@/components/app-shell";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { StatusBadge } from "@/components/status-badge";
import { deadlineStatus, fmtDate } from "@/lib/format";
import type { DeadlineType } from "@/lib/types";
import { useState } from "react";
import { toast } from "sonner";

export function DeadlinePage({ tip, title, description }: { tip: DeadlineType; title: string; description: string }) {
  const { session } = useAuth();
  const data = useData();
  const vehicles = data.vehicles.filter((v) => v.clientId === session?.clientId);
  const items = data.deadlines.filter((d) => d.clientId === session?.clientId && d.tip === tip);
  return (
    <div>
      <PageHeader title={title} description={description} />
      {vehicles.length === 0 ? (
        <EmptyState title="Nicio mașină înregistrată" description="Contactați service-ul pentru a înregistra o mașină." />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {vehicles.map((v) => {
            const it = items.find((x) => x.vehicleId === v.id);
            return (
              <Card key={v.id}>
                <CardHeader>
                  <CardTitle className="text-base">{v.marca} {v.model} <span className="text-muted-foreground font-normal text-sm">· {v.numarInmatriculare}</span></CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                  {it ? (
                    <>
                      <div className="text-sm">Expiră la <span className="font-medium">{fmtDate(it.expiraLa)}</span></div>
                      <StatusBadge tone={deadlineStatus(it.expiraLa).tone}>{deadlineStatus(it.expiraLa).label}</StatusBadge>
                    </>
                  ) : (
                    <p className="text-sm text-muted-foreground">Nicio dată înregistrată.</p>
                  )}
                  <EditDeadlineDialog tip={tip} vehicleId={v.id} existingId={it?.id} existingDate={it?.expiraLa} />
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
}

function EditDeadlineDialog({ tip, vehicleId, existingId, existingDate }: { tip: DeadlineType; vehicleId: string; existingId?: string; existingDate?: string }) {
  const { session } = useAuth();
  const [open, setOpen] = useState(false);
  const [date, setDate] = useState(existingDate ?? "");
  const save = () => {
    if (!date) return toast.error("Introduceți o dată.");
    update((d) => {
      if (existingId) {
        const it = d.deadlines.find((x) => x.id === existingId);
        if (it) it.expiraLa = date;
      } else {
        d.deadlines.push({ id: uid("d"), vehicleId, clientId: session!.clientId!, tip, expiraLa: date });
      }
    });
    audit({ autor: session!.name, rol: "client", actiune: existingId ? `Actualizat ${tip}` : `Adăugat ${tip}`, entitate: `Vehicul ${vehicleId}`, detalii: date });
    toast.success("Salvat.");
    setOpen(false);
  };
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild><Button size="sm" variant="outline">{existingId ? "Actualizează data" : "Adaugă data"}</Button></DialogTrigger>
      <DialogContent>
        <DialogHeader><DialogTitle>{existingId ? "Actualizează" : "Adaugă"} {tip}</DialogTitle></DialogHeader>
        <div className="space-y-2">
          <Label htmlFor="dt">Data expirării</Label>
          <Input id="dt" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </div>
        <DialogFooter><Button onClick={save}>Salvează</Button></DialogFooter>
      </DialogContent>
    </Dialog>
  );
}