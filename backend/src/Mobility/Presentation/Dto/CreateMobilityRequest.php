<?php

declare(strict_types=1);

namespace App\Mobility\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateMobilityRequest
{
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['REPLACEMENT_CAR', 'TAXI', 'PERSON_TRANSPORT', 'ACCOMMODATION', 'OTHER'], message: 'Tip de mobilitate invalid.')]
    public string $type = '';

    #[Assert\NotBlank(message: 'Detaliați solicitarea.')]
    #[Assert\Length(max: 5000)]
    public string $details = '';

    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $preferredDate = null;

    #[Assert\Uuid(message: 'Vehicul invalid.')]
    public ?string $vehicleId = null;
}
