<?php

declare(strict_types=1);

namespace App\Deadline\Domain;

use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Scadență a unui vehicul (ITP / RCA / taxă de drum / asistență rutieră).
 * Starea (VALID / DUE_SOON / EXPIRED / UNKNOWN) NU se stochează — se calculează
 * din `expiresAt` prin DeadlineStatusCalculator.
 */
#[ORM\Entity]
#[ORM\Table(name: 'vehicle_deadlines')]
#[ORM\Index(name: 'ix_deadlines_vehicle', columns: ['vehicle_id'])]
#[ORM\Index(name: 'ix_deadlines_expires', columns: ['expires_at'])]
class VehicleDeadline
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Vehicle $vehicle;

    #[ORM\Column(length: 32, enumType: DeadlineType::class)]
    private DeadlineType $type;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validFrom;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt;

    #[ORM\ManyToOne(targetEntity: Document::class)]
    #[ORM\JoinColumn(name: 'document_id', nullable: true, onDelete: 'SET NULL')]
    private ?Document $document = null;

    #[ORM\Column(length: 16, enumType: DeadlineSource::class)]
    private DeadlineSource $source;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'verified_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Vehicle $vehicle,
        DeadlineType $type,
        ?\DateTimeImmutable $expiresAt,
        DeadlineSource $source = DeadlineSource::CLIENT,
        ?\DateTimeImmutable $validFrom = null,
    ) {
        $this->id = Uuid::v7();
        $this->vehicle = $vehicle;
        $this->type = $type;
        $this->expiresAt = $expiresAt;
        $this->source = $source;
        $this->validFrom = $validFrom;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function vehicle(): Vehicle
    {
        return $this->vehicle;
    }

    public function type(): DeadlineType
    {
        return $this->type;
    }

    public function validFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function source(): DeadlineSource
    {
        return $this->source;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function document(): ?Document
    {
        return $this->document;
    }

    public function verifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    public function update(?\DateTimeImmutable $validFrom, ?\DateTimeImmutable $expiresAt, ?string $note): void
    {
        $this->validFrom = $validFrom;
        $this->expiresAt = $expiresAt;
        $this->note = $note;
        $this->touch();
    }

    public function attachDocument(Document $document): void
    {
        $this->document = $document;
        $this->touch();
    }

    /** Validare de către service: setează sursa SERVICE + cine/ce dată. */
    public function markVerified(User $admin): void
    {
        $this->source = DeadlineSource::SERVICE;
        $this->verifiedBy = $admin;
        $this->verifiedAt = new \DateTimeImmutable();
        $this->touch();
    }

    /**
     * Modificare de către CLIENT: validarea service-ului nu mai este valabilă —
     * datele introduse de client nu pot purta ștampila service-ului.
     */
    public function resetVerification(): void
    {
        $this->verifiedBy = null;
        $this->verifiedAt = null;
        $this->source = DeadlineSource::CLIENT;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
