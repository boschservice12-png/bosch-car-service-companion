<?php

declare(strict_types=1);

namespace App\QuoteRequest\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class QuoteResponseRequest
{
    #[Assert\NotBlank(message: 'Mesajul ofertei este obligatoriu.')]
    #[Assert\Length(min: 3, max: 10000)]
    public string $message = '';

    /** Documentul ofertei (opțional), încărcat în prealabil prin /api/documents. */
    public ?string $documentId = null;
}
