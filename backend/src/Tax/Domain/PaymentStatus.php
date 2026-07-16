<?php

declare(strict_types=1);

namespace App\Tax\Domain;

/** Starea de plată a unei taxe. */
enum PaymentStatus: string
{
    case UNPAID = 'UNPAID';
    case PAID = 'PAID';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Neplătită',
            self::PAID => 'Plătită',
        };
    }
}
