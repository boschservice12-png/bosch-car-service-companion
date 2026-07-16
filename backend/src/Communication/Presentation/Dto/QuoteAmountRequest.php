<?php

declare(strict_types=1);

namespace App\Communication\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class QuoteAmountRequest
{
    /** Suma ofertei în RON. */
    #[Assert\NotNull(message: 'Suma ofertei este obligatorie.')]
    #[Assert\PositiveOrZero(message: 'Suma nu poate fi negativă.')]
    public ?float $amount = null;

    #[Assert\Length(max: 5000)]
    public ?string $body = null;
}
