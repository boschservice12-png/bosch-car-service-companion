<?php

declare(strict_types=1);

namespace App\Roadside\Domain;

/**
 * Starea unei cereri de asistență rutieră. „FORWARDED" = marcaj intern: service-ul
 * a preluat/redirecționat cazul (contact telefonic direct — vezi decizia de proiect).
 */
enum RoadsideStatus: string
{
    case NEW = 'NEW';
    case FORWARDED = 'FORWARDED';
    case RESOLVED = 'RESOLVED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'Nouă',
            self::FORWARDED => 'Preluată de service',
            self::RESOLVED => 'Rezolvată',
            self::CANCELLED => 'Anulată',
        };
    }
}
