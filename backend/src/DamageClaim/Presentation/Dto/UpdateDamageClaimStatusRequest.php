<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateDamageClaimStatusRequest
{
    #[Assert\NotBlank(message: 'Starea este obligatorie.')]
    #[Assert\Choice(choices: ['IN_PROGRESS', 'CLOSED', 'CANCELLED'], message: 'Stare invalidă.')]
    public string $status = '';

    #[Assert\Length(max: 5000)]
    public ?string $note = null;
}
