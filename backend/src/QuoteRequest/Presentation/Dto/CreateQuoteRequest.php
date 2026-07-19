<?php

declare(strict_types=1);

namespace App\QuoteRequest\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateQuoteRequest
{
    public ?string $vehicleId = null;

    #[Assert\PositiveOrZero(message: 'Kilometrajul nu poate fi negativ.')]
    #[Assert\LessThan(3000000, message: 'Kilometraj nerealist.')]
    public ?int $mileage = null;

    #[Assert\NotBlank(message: 'Descrie simptomul sau problema.')]
    #[Assert\Length(min: 10, max: 5000, minMessage: 'Descrierea e prea scurtă (min. 10 caractere).')]
    public string $symptomDescription = '';

    #[Assert\Length(max: 5000)]
    public ?string $occurrenceConditions = null;

    public bool $vehicleDrivable = true;

    #[Assert\Length(max: 200)]
    public ?string $warningLights = null;

    #[Assert\Choice(choices: ['PHONE', 'WHATSAPP', 'APP'], message: 'Metodă de contact invalidă.')]
    public ?string $preferredContactMethod = null;

    #[Assert\Length(max: 200)]
    public ?string $preferredInterval = null;

    /** true = salvează ca ciornă (nu ajunge încă la service). */
    public bool $draft = false;
}
