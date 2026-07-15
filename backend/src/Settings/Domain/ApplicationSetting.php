<?php

declare(strict_types=1);

namespace App\Settings\Domain;

use Doctrine\ORM\Mapping as ORM;

/** Setare de aplicație (single-tenant): cheie -> valoare JSON. */
#[ORM\Entity]
#[ORM\Table(name: 'application_settings')]
class ApplicationSetting
{
    #[ORM\Id]
    #[ORM\Column(length: 120)]
    private string $key;

    /** @var mixed */
    #[ORM\Column(type: 'json')]
    private mixed $value;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $key, mixed $value)
    {
        $this->key = $key;
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function key(): string
    {
        return $this->key;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
