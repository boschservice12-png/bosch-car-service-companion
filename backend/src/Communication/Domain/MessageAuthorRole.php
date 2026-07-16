<?php

declare(strict_types=1);

namespace App\Communication\Domain;

/** Cine a scris mesajul: clientul sau service-ul. */
enum MessageAuthorRole: string
{
    case CLIENT = 'CLIENT';
    case ADMIN = 'ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::CLIENT => 'Client',
            self::ADMIN => 'Service',
        };
    }
}
