import { createFileRoute, Outlet } from "@tanstack/react-router";
import { RequireRole } from "@/components/require-role";
import { AppShell, type NavItem } from "@/components/app-shell";
import { useData } from "@/lib/db";
import {
  LayoutDashboard, Users, Car, CalendarClock, Wrench, ClipboardList,
  LifeBuoy, FileWarning, MessageSquare, Receipt, History,
} from "lucide-react";

export const Route = createFileRoute("/admin")({
  ssr: false,
  component: AdminLayout,
});

function AdminLayout() {
  const data = useData();
  const unread = data.messages.filter((m) => m.autor === "client" && !m.citit).length;
  const newOffers = data.offers.filter((o) => o.status === "nou").length;
  const nav: NavItem[] = [
    { to: "/admin", label: "Dashboard", icon: <LayoutDashboard className="h-4 w-4" /> },
    { to: "/admin/clienti", label: "Clienți", icon: <Users className="h-4 w-4" /> },
    { to: "/admin/vehicule", label: "Vehicule", icon: <Car className="h-4 w-4" /> },
    { to: "/admin/scadente", label: "Scadențe", icon: <CalendarClock className="h-4 w-4" /> },
    { to: "/admin/istoric", label: "Istoric service", icon: <Wrench className="h-4 w-4" /> },
    { to: "/admin/oferte", label: "Cereri ofertă", icon: <ClipboardList className="h-4 w-4" />, badge: newOffers || undefined },
    { to: "/admin/asistenta", label: "Asistență rutieră", icon: <LifeBuoy className="h-4 w-4" /> },
    { to: "/admin/mobilitate", label: "Mobilitate", icon: <Car className="h-4 w-4" /> },
    { to: "/admin/dosare", label: "Dosare daună", icon: <FileWarning className="h-4 w-4" /> },
    { to: "/admin/mesaje", label: "Mesaje", icon: <MessageSquare className="h-4 w-4" />, badge: unread || undefined },
    { to: "/admin/taxe", label: "Taxe", icon: <Receipt className="h-4 w-4" /> },
    { to: "/admin/audit", label: "Audit", icon: <History className="h-4 w-4" /> },
  ];
  return (
    <RequireRole role="admin">
      <AppShell title="Admin Service" nav={nav}>
        <Outlet />
      </AppShell>
    </RequireRole>
  );
}