<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/**
 * Starea REALĂ de livrare a unei notificări. O notificare NU e „trimisă" doar
 * pentru că există un rând în baza de date — SENT înseamnă fie succesul unui
 * furnizor automat, fie o confirmare manuală explicită a unui admin.
 */
enum NotificationStatus: string
{
    /** Creată, dar niciun canal nu a fost încă încercat. */
    case PENDING = 'PENDING';
    /** Un worker/furnizor o procesează chiar acum. */
    case PROCESSING = 'PROCESSING';
    /** Nu există furnizor automat — trimiterea o face un om (WhatsApp/telefon). */
    case MANUAL_ACTION_REQUIRED = 'MANUAL_ACTION_REQUIRED';
    /** Livrată: furnizor automat cu succes SAU confirmare manuală de admin. */
    case SENT = 'SENT';
    /** Încercarea de livrare a eșuat. */
    case FAILED = 'FAILED';
    /** Nu se poate/trebuie trimisă (fără destinatar, fără consimțământ, duplicat). */
    case SKIPPED = 'SKIPPED';
}
