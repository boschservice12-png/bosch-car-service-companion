import { createFileRoute } from "@tanstack/react-router";
import { DeadlinePage } from "@/components/deadline-page";
export const Route = createFileRoute("/client/rovinieta")({ component: () => <DeadlinePage tip="Rovinieta" title="Rovinietă" description="Taxa de utilizare a drumurilor naționale." /> });