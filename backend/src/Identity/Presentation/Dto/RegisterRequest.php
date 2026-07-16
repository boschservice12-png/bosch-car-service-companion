<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest
{
    #[Assert\NotBlank(message: 'Emailul este obligatoriu.')]
    #[Assert\Email(message: 'Email invalid.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'Parola este obligatorie.')]
    #[Assert\Length(min: 8, minMessage: 'Parola trebuie să aibă minim 8 caractere.')]
    public string $password = '';

    #[Assert\Length(max: 120)]
    public ?string $firstName = null;

    #[Assert\Length(max: 120)]
    public ?string $lastName = null;

    #[Assert\IsTrue(message: 'Trebuie să acceptați prelucrarea datelor.')]
    public bool $consent = false;
}
