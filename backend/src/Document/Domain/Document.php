<?php

declare(strict_types=1);

namespace App\Document\Domain;

use App\Identity\Domain\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Metadatele unui fișier privat. Conținutul NU se stochează în DB — doar în
 * storage privat, referit prin `storageKey`. Un document devine servibil doar
 * după ce scanarea antimalware îl marchează `CLEAN`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'documents')]
class Document
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 512)]
    private string $storageKey;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalName;

    #[ORM\Column(length: 120)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private int $sizeBytes;

    #[ORM\Column(length: 16, enumType: ScanStatus::class)]
    private ScanStatus $scanStatus = ScanStatus::PENDING;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $owner;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        string $storageKey,
        string $mimeType,
        int $sizeBytes,
        ?User $owner,
        ?string $originalName = null,
    ) {
        $this->id = Uuid::v7();
        $this->storageKey = $storageKey;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->owner = $owner;
        $this->originalName = $originalName;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function originalName(): ?string
    {
        return $this->originalName;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function scanStatus(): ScanStatus
    {
        return $this->scanStatus;
    }

    public function owner(): ?User
    {
        return $this->owner;
    }

    public function isServable(): bool
    {
        return $this->scanStatus === ScanStatus::CLEAN && $this->deletedAt === null;
    }

    public function markClean(): void
    {
        $this->scanStatus = ScanStatus::CLEAN;
    }

    public function markInfected(): void
    {
        $this->scanStatus = ScanStatus::INFECTED;
    }
}
