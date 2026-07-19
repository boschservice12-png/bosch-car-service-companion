<?php

declare(strict_types=1);

namespace App\DamageClaim\Domain;

use App\Document\Domain\Document;
use App\Identity\Domain\User;
use App\Shared\Domain\InvalidStateTransition;
use App\Vehicle\Domain\Vehicle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Un dosar de daună deschis de un client cu ajutorul service-ului: datele
 * evenimentului, asigurătorul, numărul poliței și fotografii/documente. Aparține
 * clientului (autorizare la nivel de obiect); service-ul îl asistă și îi urmărește starea.
 */
#[ORM\Entity]
#[ORM\Table(name: 'damage_claims')]
#[ORM\Index(name: 'ix_damage_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'ix_damage_status', columns: ['status'])]
class DamageClaim
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false)]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(name: 'vehicle_id', nullable: true, onDelete: 'SET NULL')]
    private ?Vehicle $vehicle;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $incidentDate;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $incidentLocation;

    #[ORM\Column(type: 'text')]
    private string $incidentDescription;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $insurer;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $policyNumber;

    #[ORM\Column(length: 24, enumType: DamageClaimStatus::class)]
    private DamageClaimStatus $status = DamageClaimStatus::SUBMITTED;

    /**
     * Lista documentelor pe care service-ul le-a cerut clientului (completată
     * când dosarul trece în DOCUMENTS_MISSING).
     *
     * @var string[]|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $missingDocuments = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    /** @var Collection<int, Document> */
    #[ORM\ManyToMany(targetEntity: Document::class)]
    #[ORM\JoinTable(name: 'damage_claim_documents')]
    private Collection $documents;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $customer,
        ?Vehicle $vehicle,
        ?\DateTimeImmutable $incidentDate,
        ?string $incidentLocation,
        string $incidentDescription,
        ?string $insurer,
        ?string $policyNumber,
    ) {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->vehicle = $vehicle;
        $this->incidentDate = $incidentDate;
        $this->incidentLocation = $incidentLocation;
        $this->incidentDescription = $incidentDescription;
        $this->insurer = $insurer;
        $this->policyNumber = $policyNumber;
        $this->documents = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function customer(): User
    {
        return $this->customer;
    }

    public function vehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function incidentDate(): ?\DateTimeImmutable
    {
        return $this->incidentDate;
    }

    public function incidentLocation(): ?string
    {
        return $this->incidentLocation;
    }

    public function incidentDescription(): string
    {
        return $this->incidentDescription;
    }

    public function insurer(): ?string
    {
        return $this->insurer;
    }

    public function policyNumber(): ?string
    {
        return $this->policyNumber;
    }

    public function status(): DamageClaimStatus
    {
        return $this->status;
    }

    public function note(): ?string
    {
        return $this->note;
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

    public function attach(Document $document): void
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $this->touch();
        }
    }

    public function isOpen(): bool
    {
        return $this->status === DamageClaimStatus::SUBMITTED;
    }

    /** @return string[]|null */
    public function missingDocuments(): ?array
    {
        return $this->missingDocuments;
    }

    /** @param string[]|null $missingDocuments Obligatoriu nenul la trecerea în DOCUMENTS_MISSING. */
    public function changeStatus(DamageClaimStatus $status, ?string $note, ?array $missingDocuments = null): void
    {
        if (!$this->status->canTransitionTo($status)) {
            throw InvalidStateTransition::between($this->status->value, $status->value);
        }
        $this->status = $status;
        if ($status === DamageClaimStatus::DOCUMENTS_MISSING) {
            $this->missingDocuments = $missingDocuments ?? $this->missingDocuments ?? [];
        } elseif ($missingDocuments !== null) {
            $this->missingDocuments = $missingDocuments;
        }
        if ($note !== null) {
            $this->note = $note;
        }
        $this->touch();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
