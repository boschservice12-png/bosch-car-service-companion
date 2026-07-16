<?php

declare(strict_types=1);

namespace App\ServiceHistory\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload pentru crearea/actualizarea unei ciorne de înregistrare service.
 * Toate câmpurile sunt opționale la nivel de ciornă; obligativitatea lor este
 * verificată la *publicare* (vezi ServiceRecord::missingForPublish()).
 */
final class ServiceRecordRequest
{
    #[Assert\Date(message: 'Dată invalidă (format așteptat: AAAA-LL-ZZ).')]
    public ?string $serviceDate = null;

    #[Assert\PositiveOrZero(message: 'Kilometrajul nu poate fi negativ.')]
    #[Assert\LessThanOrEqual(value: 5000000, message: 'Kilometraj neverosimil.')]
    public ?int $odometerKm = null;

    #[Assert\Length(max: 120)]
    public ?string $workType = null;

    #[Assert\Length(max: 5000)]
    public ?string $workDescription = null;

    #[Assert\Length(max: 5000)]
    public ?string $partsSummary = null;

    /** Manoperă în RON. */
    #[Assert\PositiveOrZero(message: 'Manopera nu poate fi negativă.')]
    public ?float $laborCost = null;

    /** Total în RON. */
    #[Assert\PositiveOrZero(message: 'Totalul nu poate fi negativ.')]
    public ?float $totalAmount = null;

    #[Assert\Length(max: 255)]
    public ?string $warranty = null;
}
