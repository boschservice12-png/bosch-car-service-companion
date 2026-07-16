<?php

declare(strict_types=1);

namespace App\Tax\Domain;

/** Tipul taxei/impozitului anual. */
enum TaxType: string
{
    case VEHICLE_TAX = 'VEHICLE_TAX';
    case ENVIRONMENT = 'ENVIRONMENT';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::VEHICLE_TAX => 'Impozit auto',
            self::ENVIRONMENT => 'Taxă de mediu',
            self::OTHER => 'Altă taxă',
        };
    }
}
