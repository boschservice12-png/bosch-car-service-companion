'use client';

import { BottomNav } from '@/components/BottomNav';
import { EmptyState } from '@/components/states';

export default function MessagesPage() {
  return (
    <>
      <h1>Mesaje</h1>
      <EmptyState
        title="Mesageria vine în curând"
        hint="Modulul de comunicare client–service este planificat pentru Sprint 7."
      />
      <BottomNav />
    </>
  );
}
