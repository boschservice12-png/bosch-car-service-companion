<?php

declare(strict_types=1);

namespace App\Communication\Domain;

/**
 * Starea unei conversații. Pentru mesageria generală: OPEN/CLOSED. Pentru cererile
 * de ofertă: OPEN (trimisă) → QUOTED (service a răspuns cu sumă) → ACCEPTED/DECLINED.
 */
enum ConversationStatus: string
{
    case OPEN = 'OPEN';
    case QUOTED = 'QUOTED';
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
    case CLOSED = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Deschis',
            self::QUOTED => 'Ofertă trimisă',
            self::ACCEPTED => 'Acceptată',
            self::DECLINED => 'Refuzată',
            self::CLOSED => 'Închis',
        };
    }
}
