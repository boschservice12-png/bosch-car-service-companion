import { type ReactNode, useState } from "react";
import { Link, useNavigate, useRouterState } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import { Button } from "@/components/ui/button";
import { LogOut, Menu } from "lucide-react";
import { cn } from "@/lib/utils";

export interface NavItem {
  to: string;
  label: string;
  icon: ReactNode;
  badge?: number;
}

export function AppShell({ title, nav, children }: { title: string; nav: NavItem[]; children: ReactNode }) {
  const { session, logout } = useAuth();
  const navigate = useNavigate();
  const pathname = useRouterState({ select: (s) => s.location.pathname });
  const [open, setOpen] = useState(false);

  const handleLogout = () => {
    logout();
    navigate({ to: "/" });
  };

  return (
    <div className="min-h-screen flex bg-background">
      <aside
        className={cn(
          "fixed inset-y-0 left-0 z-40 w-64 bg-sidebar text-sidebar-foreground flex flex-col transition-transform md:translate-x-0 md:static md:z-auto",
          open ? "translate-x-0" : "-translate-x-full",
        )}
      >
        <div className="px-5 py-5 border-b border-sidebar-border">
          <Link to="/" className="flex items-center gap-2">
            <span className="inline-block h-8 w-8 rounded bg-primary" aria-hidden />
            <span className="font-bold tracking-tight text-lg">Auto Service</span>
          </Link>
          <div className="mt-1 text-xs text-sidebar-foreground/60">{title}</div>
        </div>
        <nav className="flex-1 overflow-y-auto p-3 space-y-1">
          {nav.map((n) => {
            const active = pathname === n.to;
            return (
              <Link
                key={n.to}
                to={n.to}
                onClick={() => setOpen(false)}
                className={cn(
                  "flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors",
                  active
                    ? "bg-sidebar-primary text-sidebar-primary-foreground"
                    : "hover:bg-sidebar-accent hover:text-sidebar-accent-foreground",
                )}
              >
                <span className="shrink-0">{n.icon}</span>
                <span className="truncate">{n.label}</span>
                {n.badge ? (
                  <span className="ml-auto rounded-full bg-primary text-primary-foreground text-xs px-2 py-0.5">
                    {n.badge}
                  </span>
                ) : null}
              </Link>
            );
          })}
        </nav>
        <div className="p-3 border-t border-sidebar-border">
          <div className="text-xs text-sidebar-foreground/60 mb-2 truncate">{session?.name}</div>
          <Button variant="secondary" size="sm" className="w-full" onClick={handleLogout}>
            <LogOut className="h-4 w-4 mr-2" /> Ieșire
          </Button>
        </div>
      </aside>

      {open && <div className="fixed inset-0 bg-black/40 z-30 md:hidden" onClick={() => setOpen(false)} />}

      <div className="flex-1 flex flex-col min-w-0">
        <header className="sticky top-0 z-20 bg-background border-b h-14 flex items-center gap-3 px-4">
          <Button variant="ghost" size="icon" className="md:hidden" onClick={() => setOpen(true)}>
            <Menu className="h-5 w-5" />
          </Button>
          <h1 className="font-semibold truncate">{title}</h1>
        </header>
        <main className="flex-1 p-4 md:p-6 overflow-x-hidden">{children}</main>
      </div>
    </div>
  );
}

export function PageHeader({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
  return (
    <div className="mb-6 grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4">
      <div className="min-w-0">
        <h2 className="text-2xl font-bold tracking-tight truncate">{title}</h2>
        {description && <p className="text-sm text-muted-foreground mt-1">{description}</p>}
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  );
}

export function EmptyState({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
  return (
    <div className="border border-dashed rounded-lg p-10 text-center bg-card">
      <h3 className="font-medium">{title}</h3>
      {description && <p className="text-sm text-muted-foreground mt-1">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}