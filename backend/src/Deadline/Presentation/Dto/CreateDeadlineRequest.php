<?php

declare(strict_types=1);

namespace App\Deadline\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateDeadlineRequest
{
    #[Assert\NotBlank(message: 'Tipul scadenței este obligatoriu.')]
    #[Assert\Choice(choices: ['ITP', 'RCA', 'ROAD_TAX', 'ROADSIDE_ASSISTANCE'], message: 'Tip de scadență invalid.')]
    public string $type = '';

    #[Assert\NotBlank(message: 'Data expirării este obligatorie.')]
    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $expiresAt = null;

    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $validFrom = null;

    #[Assert\Length(max: 2000)]
    public ?string $note = null;
}
