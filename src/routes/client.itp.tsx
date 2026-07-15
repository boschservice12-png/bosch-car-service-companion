import { createFileRoute } from "@tanstack/react-router";
import { DeadlinePage } from "@/components/deadline-page";
export const Route = createFileRoute("/client/itp")({ component: () => <DeadlinePage tip="ITP" title="ITP" description="Inspecția tehnică periodică — status pentru fiecare mașină." /> });