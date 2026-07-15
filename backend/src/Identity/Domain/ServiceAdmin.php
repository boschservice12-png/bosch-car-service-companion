<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/** Profil pentru un operator al service-ului (rol SERVICE_ADMIN). */
#[ORM\Entity]
#[ORM\Table(name: 'service_admins')]
class ServiceAdmin
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $displayName;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, ?string $displayName = null)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->displayName = $displayName;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }
}
