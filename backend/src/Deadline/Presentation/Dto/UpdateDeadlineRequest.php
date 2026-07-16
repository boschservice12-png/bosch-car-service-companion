<?php

declare(strict_types=1);

namespace App\Deadline\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateDeadlineRequest
{
    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $expiresAt = null;

    #[Assert\Date(message: 'Dată invalidă.')]
    public ?string $validFrom = null;

    #[Assert\Length(max: 2000)]
    public ?string $note = null;

    /** Doar pentru admin: marchează scadența ca validată de service. */
    public ?bool $verify = null;
}
