<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateTaxItemRequest
{
    #[Assert\NotNull(message: 'Anul este obligatoriu.')]
    #[Assert\Range(min: 2000, max: 2100, notInRangeMessage: 'An invalid.')]
    public ?int $year = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['VEHICLE_TAX', 'ENVIRONMENT', 'OTHER'], message: 'Tip de taxă invalid.')]
    public string $type = '';

    /** Suma în RON. */
    #[Assert\NotNull(message: 'Suma este obligatorie.')]
    #[Assert\PositiveOrZero(message: 'Suma nu poate fi negativă.')]
    public ?float $amount = null;

    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $dueDate = null;

    #[Assert\Uuid(message: 'Vehicul invalid.')]
    public ?string $vehicleId = null;
}
