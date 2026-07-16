<?php

declare(strict_types=1);

namespace App\Vehicle\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateVehicleRequest
{
    #[Assert\Length(max: 16)]
    public ?string $plateNumber = null;

    #[Assert\Length(max: 80)]
    public ?string $make = null;

    #[Assert\Length(max: 80)]
    public ?string $model = null;

    #[Assert\Range(min: 1950, max: 2100)]
    public ?int $year = null;
}
