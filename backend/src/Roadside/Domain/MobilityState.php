<?php

declare(strict_types=1);

namespace App\Roadside\Domain;

/** Mașina se mai poate deplasa sau nu (relevant pentru tipul intervenției). */
enum MobilityState: string
{
    case DRIVABLE = 'DRIVABLE';
    case NOT_DRIVABLE = 'NOT_DRIVABLE';

    public function label(): string
    {
        return match ($this) {
            self::DRIVABLE => 'Se poate deplasa',
            self::NOT_DRIVABLE => 'Imobilizată',
        };
    }
}
