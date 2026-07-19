<?php

declare(strict_types=1);

namespace App\DamageClaim\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class UpdateDamageClaimStatusRequest
{
    #[Assert\NotBlank(message: 'Starea este obligatorie.')]
    #[Assert\Choice(choices: ['DOCUMENTS_MISSING', 'IN_REVIEW', 'CONTACTED', 'FILE_OPENED', 'CLOSED'], message: 'Stare invalidă.')]
    public string $status = '';

    #[Assert\Length(max: 5000)]
    public ?string $note = null;

    /**
     * Documentele cerute clientului (folosit la DOCUMENTS_MISSING).
     *
     * @var string[]|null
     */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 200)])]
    public ?array $missingDocuments = null;
}
