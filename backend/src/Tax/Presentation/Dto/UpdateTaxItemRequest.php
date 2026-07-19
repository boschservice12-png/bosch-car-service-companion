<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Editarea unei taxe de către client. Toate câmpurile sunt opționale — se
 * actualizează doar cele trimise. Șir gol la `dueDate`/`vehicleId` = golire.
 */
final class UpdateTaxItemRequest
{
    #[Assert\Range(min: 2000, max: 2100, notInRangeMessage: 'An invalid.')]
    public ?int $year = null;

    #[Assert\Choice(choices: ['VEHICLE_TAX', 'ENVIRONMENT', 'OTHER'], message: 'Tip de taxă invalid.')]
    public ?string $type = null;

    /** Suma în RON. */
    #[Assert\PositiveOrZero(message: 'Suma nu poate fi negativă.')]
    public ?float $amount = null;

    /** Format AAAA-LL-ZZ; șir gol = fără scadență. */
    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $dueDate = null;

    /** UUID de vehicul; șir gol = fără vehicul. */
    public ?string $vehicleId = null;
}
