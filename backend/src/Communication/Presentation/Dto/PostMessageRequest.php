<?php

declare(strict_types=1);

namespace App\Communication\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class PostMessageRequest
{
    #[Assert\NotBlank(message: 'Mesajul nu poate fi gol.')]
    #[Assert\Length(max: 5000)]
    public string $body = '';

    /** @var string[] */
    #[Assert\All([new Assert\Uuid(message: 'Document invalid.')])]
    public array $documentIds = [];
}
