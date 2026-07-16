<?php

declare(strict_types=1);

namespace App\Communication\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class StartConversationRequest
{
    #[Assert\NotBlank(message: 'Tipul conversației este obligatoriu.')]
    #[Assert\Choice(choices: ['GENERAL', 'QUOTE'], message: 'Tip de conversație invalid.')]
    public string $type = 'GENERAL';

    #[Assert\NotBlank(message: 'Subiectul este obligatoriu.')]
    #[Assert\Length(max: 200)]
    public string $subject = '';

    #[Assert\NotBlank(message: 'Mesajul nu poate fi gol.')]
    #[Assert\Length(max: 5000)]
    public string $body = '';

    #[Assert\Uuid(message: 'Vehicul invalid.')]
    public ?string $vehicleId = null;

    /** @var string[] */
    #[Assert\All([new Assert\Uuid(message: 'Document invalid.')])]
    public array $documentIds = [];
}
