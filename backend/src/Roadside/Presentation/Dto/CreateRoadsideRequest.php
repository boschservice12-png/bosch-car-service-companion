<?php

declare(strict_types=1);

namespace App\Roadside\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateRoadsideRequest
{
    #[Assert\NotBlank(message: 'Locația este obligatorie.')]
    #[Assert\Length(max: 500)]
    public string $location = '';

    #[Assert\NotBlank(message: 'Descrieți problema.')]
    #[Assert\Length(max: 5000)]
    public string $problem = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['DRIVABLE', 'NOT_DRIVABLE'], message: 'Valoare invalidă pentru mobilitate.')]
    public string $mobility = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['SAFE', 'AT_RISK'], message: 'Valoare invalidă pentru siguranță.')]
    public string $safety = '';

    #[Assert\NotBlank(message: 'Numărul de telefon este obligatoriu.')]
    #[Assert\Length(max: 40)]
    public string $phone = '';

    #[Assert\Uuid(message: 'Vehicul invalid.')]
    public ?string $vehicleId = null;
}
