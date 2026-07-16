<?php

declare(strict_types=1);

namespace App\Mobility\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateMobilityStatusRequest
{
    #[Assert\NotBlank(message: 'Starea este obligatorie.')]
    #[Assert\Choice(choices: ['APPROVED', 'PROVIDED', 'DECLINED', 'CANCELLED'], message: 'Stare invalidă.')]
    public string $status = '';

    #[Assert\Length(max: 5000)]
    public ?string $note = null;
}
