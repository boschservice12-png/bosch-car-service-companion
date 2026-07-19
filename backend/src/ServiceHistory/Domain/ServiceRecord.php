<?php

declare(strict_types=1);

namespace App\ServiceHistory\Domain;

use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * O intrare din istoricul de service al unui vehicul.
 *
 * Reguli de integritate:
 *  - o înregistrare PUBLICATĂ este imutabilă (nu se poate suprascrie „în tăcere");
 *    o modificare se face prin crearea unei *corecții* — o înregistrare nouă care
 *    referă originalul (`correctionOf`), astfel încât ambele rămân vizibile;
 *  - sumele (manoperă, total) sunt stocate în bani (întreg) pentru a evita
 *    erorile de virgulă mobilă și pentru portabilitate SQLite/PostgreSQL.
 */
#[ORM\Entity]
#[ORM\Table(name: 'service_records')]
#[ORM\Index(name: 'ix_service_records_vehicle', columns: ['vehicle_id'])]
#[ORM\Index(name: 'ix_service_records_status', columns: ['status'])]
class ServiceRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Vehicle $vehicle;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $serviceDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $odometerKm = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $workType = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $workDescription = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $partsSummary = null;

    /** Manoperă în bani (RON * 100). */
    #[ORM\Column]
    private int $laborBani = 0;

    /** Total în bani (RON * 100). */
    #[ORM\Column]
    private int $totalBani = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $warranty = null;

    #[ORM\Column(length: 16, enumType: ServiceRecordStatus::class)]
    private ServiceRecordStatus $status = ServiceRecordStatus::DRAFT;

    /** Dacă e setat, această înregistrare corectează una publicată anterior. */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'correction_of_id', nullable: true, onDelete: 'SET NULL')]
    private ?ServiceRecord $correctionOf = null;

    /** Motivul corecției (obligatoriu la creare) — apare și în audit și către client. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $correctionReason = null;

    /** @var Collection<int, Document> */
    #[ORM\ManyToMany(targetEntity: Document::class)]
    #[ORM\JoinTable(name: 'service_record_documents')]
    private Collection $documents;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetimetz_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Vehicle $vehicle, ?User $createdBy = null, ?ServiceRecord $correctionOf = null, ?string $correctionReason = null)
    {
        $this->id = Uuid::v7();
        $this->vehicle = $vehicle;
        $this->createdBy = $createdBy;
        $this->correctionOf = $correctionOf;
        $this->correctionReason = $correctionReason;
        $this->documents = new ArrayCollection();
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

    public function serviceDate(): ?\DateTimeImmutable
    {
        return $this->serviceDate;
    }

    public function odometerKm(): ?int
    {
        return $this->odometerKm;
    }

    public function workType(): ?string
    {
        return $this->workType;
    }

    public function workDescription(): ?string
    {
        return $this->workDescription;
    }

    public function partsSummary(): ?string
    {
        return $this->partsSummary;
    }

    public function laborBani(): int
    {
        return $this->laborBani;
    }

    public function totalBani(): int
    {
        return $this->totalBani;
    }

    public function warranty(): ?string
    {
        return $this->warranty;
    }

    public function status(): ServiceRecordStatus
    {
        return $this->status;
    }

    public function isDraft(): bool
    {
        return $this->status === ServiceRecordStatus::DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === ServiceRecordStatus::PUBLISHED;
    }

    public function correctionReason(): ?string
    {
        return $this->correctionReason;
    }

    public function correctionOf(): ?ServiceRecord
    {
        return $this->correctionOf;
    }

    /** @return Document[] */
    public function documents(): array
    {
        return $this->documents->toArray();
    }

    public function hasDocument(Document $document): bool
    {
        return $this->documents->contains($document);
    }

    public function createdBy(): ?User
    {
        return $this->createdBy;
    }

    public function publishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Aplică valorile pe o ciornă. Aruncă dacă înregistrarea e publicată —
     * o publicată nu poate fi rescrisă (vezi createCorrection în serviciu).
     */
    public function applyDetails(
        ?\DateTimeImmutable $serviceDate,
        ?int $odometerKm,
        ?string $workType,
        ?string $workDescription,
        ?string $partsSummary,
        int $laborBani,
        int $totalBani,
        ?string $warranty,
    ): void {
        $this->assertMutable();
        $this->serviceDate = $serviceDate;
        $this->odometerKm = $odometerKm;
        $this->workType = $workType;
        $this->workDescription = $workDescription;
        $this->partsSummary = $partsSummary;
        $this->laborBani = $laborBani;
        $this->totalBani = $totalBani;
        $this->warranty = $warranty;
        $this->touch();
    }

    public function attachDocument(Document $document): void
    {
        $this->assertMutable();
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $this->touch();
        }
    }

    public function publish(): void
    {
        if ($this->status === ServiceRecordStatus::PUBLISHED) {
            return;
        }
        if ($this->status === ServiceRecordStatus::CORRECTED) {
            throw new \LogicException('O înregistrare corectată (înlocuită) nu se republică.');
        }
        $this->status = ServiceRecordStatus::PUBLISHED;
        $this->publishedAt = new \DateTimeImmutable();
        $this->touch();
    }

    /**
     * Originalul devine CORRECTED când corecția lui este publicată. Rândul
     * rămâne neschimbat și vizibil (specificație: nimic nu se șterge silențios).
     */
    public function markCorrected(): void
    {
        if ($this->status !== ServiceRecordStatus::PUBLISHED) {
            throw new \LogicException('Doar o înregistrare publicată poate fi marcată drept corectată.');
        }
        $this->status = ServiceRecordStatus::CORRECTED;
        $this->touch();
    }

    /** Câmpurile obligatorii pentru publicare. @return string[] listă câmpuri lipsă */
    public function missingForPublish(): array
    {
        $missing = [];
        if ($this->serviceDate === null) {
            $missing[] = 'serviceDate';
        }
        if ($this->odometerKm === null) {
            $missing[] = 'odometerKm';
        }
        if ($this->workType === null || trim($this->workType) === '') {
            $missing[] = 'workType';
        }
        if ($this->workDescription === null || trim($this->workDescription) === '') {
            $missing[] = 'workDescription';
        }

        return $missing;
    }

    private function assertMutable(): void
    {
        if ($this->status === ServiceRecordStatus::PUBLISHED) {
            throw new \DomainException('O înregistrare publicată nu poate fi modificată.');
        }
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
