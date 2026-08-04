<?php

declare(strict_types=1);

namespace App\Notification\Domain;

/**
 * Port pentru livrarea unei notificări pe un canal. Implementarea implicită în
 * pilot (ManualNotificationDelivery) NU are furnizor automat: întoarce
 * MANUAL_ACTION_REQUIRED sau SKIPPED, niciodată SENT. Când se adaugă un
 * furnizor real (mailer etc.), acesta întoarce sent()/failed() după rezultatul
 * efectiv al trimiterii.
 */
interface NotificationDelivery
{
    public function deliver(Notification $notification): NotificationDeliveryResult;
}
