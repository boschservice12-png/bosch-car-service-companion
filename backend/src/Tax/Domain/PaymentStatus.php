<?php

declare(strict_types=1);

namespace App\Tax\Domain;

/**
 * Starea de plată a unei taxe — conform specificației: UNPAID, PARTIALLY_PAID,
 * PAID, OVERDUE. OVERDUE este derivată (scadență depășită fără plată integrală)
 * prin TaxItem::effectiveStatus(), nu se setează manual.
 */
enum PaymentStatus: string
{
    case UNPAID = 'UNPAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Neplătită',
            self::PARTIALLY_PAID => 'Parțial plătită',
            self::PAID => 'Plătită',
            self::OVERDUE => 'Restantă',
        };
    }
}
