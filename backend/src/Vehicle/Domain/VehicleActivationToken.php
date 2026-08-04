<?php

declare(strict_types=1);

namespace App\Vehicle\Domain;

use App\Identity\Domain\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Cod de activare pentru un vehicul (Blocul 3). Adminul îl emite; clientul îl
 * folosește O SINGURĂ dată pentru a deveni proprietar activ. Dovada NU e
 * numărul de înmatriculare (nu e secret), ci acest cod cu entropie mare.
 *
 * În baza de date se stochează DOAR hash-ul (SHA-256), niciodată codul în clar.
 */
#[ORM\Entity]
#[ORM\Table(name: 'vehicle_activation_tokens')]
#[ORM\Index(name: 'ix_vat_vehicle', columns: ['vehicle_id'])]
#[ORM\UniqueConstraint(name: 'ux_vat_token_hash', columns: ['token_hash'])]
class VehicleActivationToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: false)]
    private Vehicle $vehicle;

    #[ORM\Column(length: 64)]
    private string $tokenHash;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'used_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $usedBy = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $attemptCount = 0;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    public function __construct(Vehicle $vehicle, string $tokenHash, ?User $createdBy, \DateTimeImmutable $expiresAt)
    {
        $this->id = Uuid::v7();
        $this->vehicle = $vehicle;
        $this->tokenHash = $tokenHash;
        $this->createdBy = $createdBy;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function vehicle(): Vehicle
    {
        return $this->vehicle;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isLive(\DateTimeImmutable $now): bool
    {
        return $this->usedAt === null && $this->revokedAt === null && $this->expiresAt > $now;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function registerAttempt(): void
    {
        ++$this->attemptCount;
        $this->lastAttemptAt = new \DateTimeImmutable();
    }

    public function markUsed(User $user): void
    {
        $this->usedAt = new \DateTimeImmutable();
        $this->usedBy = $user;
    }

    public function revoke(): void
    {
        if ($this->usedAt === null) {
            $this->revokedAt = new \DateTimeImmutable();
        }
    }
}
