<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

/** Stare calculată a unei scadențe (nu se stochează ca sursă de adevăr). */
enum DeadlineState: string
{
    case UNKNOWN = 'UNKNOWN';
    case VALID = 'VALID';
    case DUE_SOON = 'DUE_SOON';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::UNKNOWN => 'Necunoscut',
            self::VALID => 'Valabil',
            self::DUE_SOON => 'Expiră curând',
            self::EXPIRED => 'Expirat',
        };
    }
}
