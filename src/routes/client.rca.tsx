import { createFileRoute } from "@tanstack/react-router";
import { DeadlinePage } from "@/components/deadline-page";
export const Route = createFileRoute("/client/rca")({ component: () => <DeadlinePage tip="RCA" title="RCA" description="Asigurarea obligatorie de răspundere civilă auto." /> });