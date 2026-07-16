<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateDamageClaimRequest
{
    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $incidentDate = null;

    #[Assert\Length(max: 500)]
    public ?string $incidentLocation = null;

    #[Assert\NotBlank(message: 'Descrieți evenimentul.')]
    #[Assert\Length(max: 5000)]
    public string $incidentDescription = '';

    #[Assert\Length(max: 200)]
    public ?string $insurer = null;

    #[Assert\Length(max: 100)]
    public ?string $policyNumber = null;

    #[Assert\Uuid(message: 'Vehicul invalid.')]
    public ?string $vehicleId = null;

    /** @var string[] */
    #[Assert\All([new Assert\Uuid(message: 'Document invalid.')])]
    public array $documentIds = [];
}
