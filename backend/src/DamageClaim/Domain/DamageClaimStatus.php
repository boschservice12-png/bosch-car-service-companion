<?php

declare(strict_types=1);

namespace App\DamageClaim\Domain;

/** Starea unui dosar de daună (asistență la deschidere și urmărire). */
enum DamageClaimStatus: string
{
    case NEW = 'NEW';
    case IN_PROGRESS = 'IN_PROGRESS';
    case CLOSED = 'CLOSED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Nou',
            self::IN_PROGRESS => 'În lucru',
            self::CLOSED => 'Închis',
            self::CANCELLED => 'Anulat',
        };
    }
}
