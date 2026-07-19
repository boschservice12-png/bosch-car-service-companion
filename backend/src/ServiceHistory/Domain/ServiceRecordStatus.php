<?php

declare(strict_types=1);

namespace App\ServiceHistory\Domain;

/** Starea unei înregistrări de service: ciornă (editabilă) sau publicată (imutabilă). */
enum ServiceRecordStatus: string
{
    case DRAFT = 'DRAFT';
    case PUBLISHED = 'PUBLISHED';
    case CORRECTED = 'CORRECTED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Ciornă',
            self::PUBLISHED => 'Publicat',
            self::CORRECTED => 'Corectat',
        };
    }
}
