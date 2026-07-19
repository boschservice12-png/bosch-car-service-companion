<?php

declare(strict_types=1);

namespace App\Roadside\Domain;

use App\Document\Domain\Document;
use App\Shared\Domain\InvalidStateTransition;
use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * O cerere de asistență rutieră deschisă de un client. Aparține clientului
 * (autorizare la nivel de obiect); service-ul gestionează starea. Preluarea
 * efectivă se face telefonic (marcaj intern FORWARDED + numărul de contact).
 */
#[ORM\Entity]
#[ORM\Table(name: 'roadside_requests')]
#[ORM\Index(name: 'ix_roadside_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'ix_roadside_status', columns: ['status'])]
class RoadsideRequest
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

    #[ORM\Column(length: 500)]
    private string $location;

    #[ORM\Column(type: 'text')]
    private string $problem;

    #[ORM\Column(length: 16, enumType: MobilityState::class)]
    private MobilityState $mobility;

    #[ORM\Column(length: 16, enumType: SafetyState::class)]
    private SafetyState $safety;

    #[ORM\Column(length: 40)]
    private string $phone;

    #[ORM\Column(length: 16, enumType: RoadsideStatus::class)]
    private RoadsideStatus $status = RoadsideStatus::SUBMITTED;

    /** Notă internă a service-ului (nu se ascunde clientului, dar e completată de admin). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    /** @var Collection<int, Document> */
    #[ORM\ManyToMany(targetEntity: Document::class)]
    #[ORM\JoinTable(name: 'roadside_request_documents')]
    private Collection $documents;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $customer,
        ?Vehicle $vehicle,
        string $location,
        string $problem,
        MobilityState $mobility,
        SafetyState $safety,
        string $phone,
    ) {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->vehicle = $vehicle;
        $this->location = $location;
        $this->problem = $problem;
        $this->mobility = $mobility;
        $this->safety = $safety;
        $this->phone = $phone;
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

    public function location(): string
    {
        return $this->location;
    }

    public function problem(): string
    {
        return $this->problem;
    }

    public function mobility(): MobilityState
    {
        return $this->mobility;
    }

    public function safety(): SafetyState
    {
        return $this->safety;
    }

    public function phone(): string
    {
        return $this->phone;
    }

    public function status(): RoadsideStatus
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

    public function changeStatus(RoadsideStatus $status, ?string $note): void
    {
        if (!$this->status->canTransitionTo($status)) {
            throw InvalidStateTransition::between($this->status->value, $status->value);
        }
        $this->status = $status;
        if ($note !== null) {
            $this->note = $note;
        }
        $this->touch();
    }

    public function isOpen(): bool
    {
        return $this->status === RoadsideStatus::SUBMITTED;
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
