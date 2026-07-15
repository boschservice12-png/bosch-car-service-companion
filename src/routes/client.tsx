import { createFileRoute, Outlet } from "@tanstack/react-router";
import { RequireRole } from "@/components/require-role";
import { AppShell, type NavItem } from "@/components/app-shell";
import { useAuth } from "@/lib/auth";
import { useData } from "@/lib/db";
import {
  Home, MessageSquare, ShieldCheck, FileText, Route as RouteIcon,
  Wrench, ClipboardList, LifeBuoy, Car, FileWarning, Receipt, User,
} from "lucide-react";

export const Route = createFileRoute("/client")({
  ssr: false,
  component: ClientLayout,
});

function ClientLayout() {
  const { session } = useAuth();
  const data = useData();
  const unread = data.messages.filter((m) => m.clientId === session?.clientId && m.autor === "admin" && !m.citit).length;
  const nav: NavItem[] = [
    { to: "/client", label: "Acasă", icon: <Home className="h-4 w-4" /> },
    { to: "/client/mesaje", label: "Mesaje", icon: <MessageSquare className="h-4 w-4" />, badge: unread || undefined },
    { to: "/client/itp", label: "ITP", icon: <ShieldCheck className="h-4 w-4" /> },
    { to: "/client/rca", label: "RCA", icon: <FileText className="h-4 w-4" /> },
    { to: "/client/rovinieta", label: "Rovinietă", icon: <RouteIcon className="h-4 w-4" /> },
    { to: "/client/asistenta", label: "Asistență rutieră", icon: <LifeBuoy className="h-4 w-4" /> },
    { to: "/client/istoric", label: "Istoric service", icon: <Wrench className="h-4 w-4" /> },
    { to: "/client/oferta", label: "Cere ofertă", icon: <ClipboardList className="h-4 w-4" /> },
    { to: "/client/asistenta-rutiera", label: "Solicită asistență", icon: <LifeBuoy className="h-4 w-4" /> },
    { to: "/client/mobilitate", label: "Solicită mobilitate", icon: <Car className="h-4 w-4" /> },
    { to: "/client/dosar-dauna", label: "Dosar de daună", icon: <FileWarning className="h-4 w-4" /> },
    { to: "/client/taxe", label: "Taxe și impozite", icon: <Receipt className="h-4 w-4" /> },
    { to: "/client/profil", label: "Profil / documente", icon: <User className="h-4 w-4" /> },
  ];
  return (
    <RequireRole role="client">
      <AppShell title="Portal Client" nav={nav}>
        <Outlet />
      </AppShell>
    </RequireRole>
  );
}