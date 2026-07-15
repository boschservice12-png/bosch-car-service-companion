import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useRef, useState } from "react";
import { useData, update, audit, uid } from "@/lib/db";
import { PageHeader } from "@/components/app-shell";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { fmtDateTime } from "@/lib/format";
import { useAuth } from "@/lib/auth";
import { toast } from "sonner";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/admin/mesaje")({ component: Page });

function Page() {
  const { session } = useAuth();
  const data = useData();
  const [selected, setSelected] = useState<string>(data.clients[0]?.id ?? "");
  const [text, setText] = useState("");
  const bottomRef = useRef<HTMLDivElement>(null);
  const mesaje = data.messages.filter((m) => m.clientId === selected).sort((a, b) => a.timestamp.localeCompare(b.timestamp));

  useEffect(() => {
    if (!selected) return;
    update((d) => { d.messages.forEach((m) => { if (m.clientId === selected && m.autor === "client" && !m.citit) m.citit = true; }); });
  }, [selected]);
  useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: "smooth" }); }, [mesaje.length]);

  const trimite = () => {
    if (!text.trim()) return toast.error("Scrieți un mesaj.");
    update((d) => d.messages.push({ id: uid("m"), clientId: selected, autor: "admin", autorNume: session!.name, text: text.trim(), timestamp: new Date().toISOString(), citit: false }));
    audit({ autor: session!.name, rol: "admin", actiune: "Mesaj trimis către client", entitate: `Client ${selected}` });
    setText("");
  };

  return (
    <div>
      <PageHeader title="Mesaje" description="Conversații cu clienții." />
      <div className="grid gap-4 md:grid-cols-[240px_minmax(0,1fr)]">
        <Card className="p-2 max-h-[70vh] overflow-y-auto">
          {data.clients.map((c) => {
            const unread = data.messages.filter((m) => m.clientId === c.id && m.autor === "client" && !m.citit).length;
            return (
              <button key={c.id} onClick={() => setSelected(c.id)} className={cn("w-full text-left px-3 py-2 rounded hover:bg-muted grid grid-cols-[minmax(0,1fr)_auto] items-center gap-2", selected === c.id && "bg-muted")}>
                <span className="truncate text-sm">{c.prenume} {c.nume}</span>
                {unread > 0 && <span className="text-xs bg-primary text-primary-foreground rounded-full px-2">{unread}</span>}
              </button>
            );
          })}
        </Card>
        <Card className="flex flex-col h-[70vh]">
          <div className="flex-1 overflow-y-auto p-4 space-y-3">
            {mesaje.length === 0 && <p className="text-sm text-muted-foreground">Nicio conversație.</p>}
            {mesaje.map((m) => (
              <div key={m.id} className={`flex ${m.autor === "admin" ? "justify-end" : "justify-start"}`}>
                <div className={`max-w-[75%] rounded-lg p-3 ${m.autor === "admin" ? "bg-primary text-primary-foreground" : "bg-muted"}`}>
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
    </div>
  );
}