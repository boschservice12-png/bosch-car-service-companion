<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateVehicleRequest
{
    #[Assert\NotBlank(message: 'VIN-ul este obligatoriu.')]
    #[Assert\Length(exactly: 17, exactMessage: 'VIN-ul trebuie să aibă exact 17 caractere.')]
    public string $vin = '';

    #[Assert\NotBlank(message: 'Numărul de înmatriculare este obligatoriu.')]
    #[Assert\Length(max: 16)]
    public string $plateNumber = '';

    #[Assert\Length(max: 80)]
    public ?string $make = null;

    #[Assert\Length(max: 80)]
    public ?string $model = null;

    #[Assert\Range(min: 1950, max: 2100, notInRangeMessage: 'An invalid.')]
    public ?int $year = null;
}
