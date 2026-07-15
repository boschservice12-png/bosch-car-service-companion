import { useEffect, type ReactNode } from "react";
import { useNavigate } from "@tanstack/react-router";
import { useAuth } from "@/lib/auth";
import type { Role } from "@/lib/types";

export function RequireRole({ role, children }: { role: Role; children: ReactNode }) {
  const { session, ready } = useAuth();
  const navigate = useNavigate();
  useEffect(() => {
    if (!ready) return;
    if (!session || session.role !== role) {
      navigate({ to: "/" });
    }
  }, [ready, session, role, navigate]);
  if (!ready) return <div className="p-10 text-muted-foreground">Se încarcă…</div>;
  if (!session || session.role !== role) return null;
  return <>{children}</>;
}