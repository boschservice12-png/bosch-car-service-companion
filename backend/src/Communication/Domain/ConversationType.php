<?php

declare(strict_types=1);

namespace App\Communication\Domain;

/** Tipul unei conversații: mesagerie generală sau cerere de ofertă (reparație). */
enum ConversationType: string
{
    case GENERAL = 'GENERAL';
    case QUOTE = 'QUOTE';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'Mesaj',
            self::QUOTE => 'Cerere de ofertă',
        };
    }
}
