import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import type { Role } from "./types";
import { getData } from "./db";

export interface Session {
  role: Role;
  clientId?: string;
  name: string;
}

const KEY = "bcs_session_v1";
const AuthCtx = createContext<{
  session: Session | null;
  login: (s: Session) => void;
  logout: () => void;
  ready: boolean;
}>({ session: null, login: () => {}, logout: () => {}, ready: false });

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null);
  const [ready, setReady] = useState(false);
  useEffect(() => {
    try {
      const raw = localStorage.getItem(KEY);
      if (raw) setSession(JSON.parse(raw));
    } catch {
      /* ignore */
    }
    setReady(true);
  }, []);
  const login = (s: Session) => {
    localStorage.setItem(KEY, JSON.stringify(s));
    setSession(s);
  };
  const logout = () => {
    localStorage.removeItem(KEY);
    setSession(null);
  };
  return <AuthCtx.Provider value={{ session, login, logout, ready }}>{children}</AuthCtx.Provider>;
}

export function useAuth() {
  return useContext(AuthCtx);
}

export function useCurrentClient() {
  const { session } = useAuth();
  if (!session?.clientId) return null;
  return getData().clients.find((c) => c.id === session.clientId) ?? null;
}