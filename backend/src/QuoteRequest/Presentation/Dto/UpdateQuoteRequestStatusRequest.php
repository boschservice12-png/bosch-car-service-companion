<?php

declare(strict_types=1);

namespace App\QuoteRequest\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Stările pe care le poate seta service-ul; restul aparțin clientului. */
final class UpdateQuoteRequestStatusRequest
{
    #[Assert\NotBlank(message: 'Starea este obligatorie.')]
    #[Assert\Choice(choices: ['IN_REVIEW', 'NEEDS_INFORMATION', 'CLOSED'], message: 'Stare invalidă.')]
    public string $status = '';
}
