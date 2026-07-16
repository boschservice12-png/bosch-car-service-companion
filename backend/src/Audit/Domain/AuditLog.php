<?php

declare(strict_types=1);

namespace App\Audit\Domain;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'ix_audit_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'ix_audit_created', columns: ['created_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $actorId;

    #[ORM\Column(length: 120)]
    private string $action;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $entityType;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $entityId;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $before;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $after;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ip;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        ?Uuid $actorId,
        string $action,
        ?string $entityType = null,
        ?Uuid $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $ip = null,
    ) {
        $this->id = Uuid::v7();
        $this->actorId = $actorId;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->before = $before;
        $this->after = $after;
        $this->ip = $ip;
        $this->createdAt = new \DateTimeImmutable();
    }
}
