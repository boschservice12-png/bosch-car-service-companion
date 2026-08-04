<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationDelivery;
use App\Notification\Domain\NotificationDeliveryResult;

/**
 * Livrare implicită în pilot: NU există furnizor automat (fără mailer/push/API
 * WhatsApp plătit — decizie de produs). De aceea nu marchează NICIODATĂ SENT:
 *  - dacă destinatarul are un contact real → MANUAL_ACTION_REQUIRED (operatorul
 *    trimite prin fluxul manual WhatsApp/email de pe numărul service-ului);
 *  - dacă contul e intern/anonimizat (fără persoană de contact) → SKIPPED.
 *
 * Când se adaugă un furnizor automat, se implementează un alt NotificationDelivery
 * care întoarce sent()/failed() după rezultatul real.
 */
final class ManualNotificationDelivery implements NotificationDelivery
{
    private const INTERNAL_SUFFIXES = ['@clienti.local', '@anonim.local'];

    public function deliver(Notification $notification): NotificationDeliveryResult
    {
        $email = $notification->user()->getEmail();
        foreach (self::INTERNAL_SUFFIXES as $suffix) {
            if (str_ends_with($email, $suffix)) {
                return NotificationDeliveryResult::skipped('destinatar intern/anonimizat');
            }
        }

        return NotificationDeliveryResult::manual('fără furnizor automat — trimitere manuală de către operator');
    }
}
