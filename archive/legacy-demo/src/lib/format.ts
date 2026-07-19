export function fmtDate(iso: string | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString("ro-RO", { day: "2-digit", month: "2-digit", year: "numeric" });
}

export function fmtDateTime(iso: string | undefined): string {
  if (!iso) return "—";
  const d = new Date(iso);
  return d.toLocaleString("ro-RO", { day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit" });
}

export function fmtRON(n: number | undefined): string {
  if (n == null) return "—";
  return new Intl.NumberFormat("ro-RO", { style: "currency", currency: "RON", maximumFractionDigits: 0 }).format(n);
}

export function daysUntil(iso: string): number {
  const d = new Date(iso).getTime();
  const now = Date.now();
  return Math.ceil((d - now) / (1000 * 60 * 60 * 24));
}

export function deadlineStatus(iso: string): { label: string; tone: "ok" | "warn" | "danger" } {
  const days = daysUntil(iso);
  if (days < 0) return { label: `Expirat de ${Math.abs(days)} zile`, tone: "danger" };
  if (days <= 30) return { label: `Expiră în ${days} zile`, tone: "warn" };
  return { label: `Valid — ${days} zile rămase`, tone: "ok" };
}