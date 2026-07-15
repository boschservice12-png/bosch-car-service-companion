import { cn } from "@/lib/utils";

export function StatusBadge({
  tone = "neutral",
  children,
}: {
  tone?: "ok" | "warn" | "danger" | "info" | "neutral";
  children: React.ReactNode;
}) {
  const cls = {
    ok: "bg-emerald-100 text-emerald-800",
    warn: "bg-amber-100 text-amber-800",
    danger: "bg-red-100 text-red-800",
    info: "bg-blue-100 text-blue-800",
    neutral: "bg-muted text-muted-foreground",
  }[tone];
  return (
    <span className={cn("inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium", cls)}>
      {children}
    </span>
  );
}