<?php

declare(strict_types=1);

namespace App\Mobility\Domain;

/** Starea unei solicitări de mobilitate. */
enum MobilityStatus: string
{
    case NEW = 'NEW';
    case APPROVED = 'APPROVED';
    case PROVIDED = 'PROVIDED';
    case DECLINED = 'DECLINED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Nouă',
            self::APPROVED => 'Aprobată',
            self::PROVIDED => 'Asigurată',
            self::DECLINED => 'Respinsă',
            self::CANCELLED => 'Anulată',
        };
    }
}
