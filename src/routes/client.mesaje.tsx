import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useRef, useState } from "react";
import { useAuth } from "@/lib/auth";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { fmtDateTime } from "@/lib/format";
import { toast } from "sonner";

export const Route = createFileRoute("/client/mesaje")({ component: Mesaje });

function Mesaje() {
  const { session } = useAuth();
  const data = useData();
  const [text, setText] = useState("");
  const bottomRef = useRef<HTMLDivElement>(null);
  const mesaje = data.messages
    .filter((m) => m.clientId === session?.clientId)
    .sort((a, b) => a.timestamp.localeCompare(b.timestamp));

  useEffect(() => {
    // marchează mesajele de la admin ca citite
    update((d) => {
      d.messages.forEach((m) => {
        if (m.clientId === session?.clientId && m.autor === "admin" && !m.citit) m.citit = true;
      });
    });
  }, [session?.clientId]);

  useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: "smooth" }); }, [mesaje.length]);

  const trimite = () => {
    const t = text.trim();
    if (!t) return toast.error("Scrieți un mesaj.");
    if (t.length > 2000) return toast.error("Mesajul este prea lung.");
    update((d) => {
      d.messages.push({
        id: uid("m"),
        clientId: session!.clientId!,
        autor: "client",
        autorNume: session!.name,
        text: t,
        timestamp: new Date().toISOString(),
        citit: false,
      });
    });
    audit({ autor: session!.name, rol: "client", actiune: "Mesaj trimis către service", entitate: "Mesaj" });
    setText("");
  };

  return (
    <div className="max-w-3xl">
      <PageHeader title="Mesaje" description="Comunicare directă cu echipa service-ului." />
      <Card className="flex flex-col h-[70vh]">
        <div className="flex-1 overflow-y-auto p-4 space-y-3">
          {mesaje.length === 0 && <p className="text-sm text-muted-foreground">Nu există mesaje. Începeți o conversație.</p>}
          {mesaje.map((m) => (
            <div key={m.id} className={`flex ${m.autor === "client" ? "justify-end" : "justify-start"}`}>
              <div className={`max-w-[75%] rounded-lg p-3 ${m.autor === "client" ? "bg-primary text-primary-foreground" : "bg-muted"}`}>
                <div className="text-xs opacity-75 mb-1">{m.autorNume} · {fmtDateTime(m.timestamp)}</div>
                <div className="whitespace-pre-wrap text-sm">{m.text}</div>
              </div>
            </div>
          ))}
          <div ref={bottomRef} />
        </div>
        <div className="border-t p-3 flex gap-2">
          <Textarea value={text} onChange={(e) => setText(e.target.value)} placeholder="Scrieți un mesaj…" rows={2} className="resize-none" />
          <Button onClick={trimite}>Trimite</Button>
        </div>
      </Card>
    </div>
  );
}