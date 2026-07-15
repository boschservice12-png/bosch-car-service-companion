import { createFileRoute } from "@tanstack/react-router";
import { DeadlinePage } from "@/components/deadline-page";
export const Route = createFileRoute("/client/asistenta")({ component: () => <DeadlinePage tip="Asistenta" title="Valabilitate asistență rutieră" description="Perioada în care aveți acoperire asistență rutieră." /> });