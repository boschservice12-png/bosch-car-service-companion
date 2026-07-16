<?php

declare(strict_types=1);

namespace App\Tax\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateTaxStatusRequest
{
    #[Assert\NotBlank(message: 'Starea este obligatorie.')]
    #[Assert\Choice(choices: ['PAID', 'UNPAID'], message: 'Stare invalidă.')]
    public string $status = '';

    #[Assert\Length(max: 5000)]
    public ?string $note = null;
}
