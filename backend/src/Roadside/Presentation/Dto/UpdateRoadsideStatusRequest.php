<?php

declare(strict_types=1);

namespace App\Roadside\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateRoadsideStatusRequest
{
    #[Assert\NotBlank(message: 'Starea este obligatorie.')]
    #[Assert\Choice(choices: ['FORWARDED', 'RESOLVED', 'CANCELLED'], message: 'Stare invalidă.')]
    public string $status = '';

    #[Assert\Length(max: 5000)]
    public ?string $note = null;
}
