<?php

declare(strict_types=1);

namespace App\QuoteRequest\Domain;

use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * O cerere de ofertă de preț pentru reparații (funcționalitatea 7 din
 * specificație). Aparține clientului (autorizare la nivel de obiect); starea
 * urmează mașina de stări din specificație (QuoteRequestStatus), iar orice
 * tranziție nepermisă este respinsă cu 409.
 */
#[ORM\Entity]
#[ORM\Table(name: 'quote_requests')]
#[ORM\Index(name: 'ix_quote_request_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'ix_quote_request_status', columns: ['status'])]
#[ORM\Index(name: 'ix_quote_request_created', columns: ['created_at'])]
class QuoteRequest
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

    #[ORM\Column(nullable: true)]
    private ?int $mileage;

    #[ORM\Column(type: 'text')]
    private string $symptomDescription;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $occurrenceConditions;

    #[ORM\Column]
    private bool $vehicleDrivable;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $warningLights;

    /** PHONE / WHATSAPP / APP — canalul preferat de contact. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $preferredContactMethod;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $preferredInterval;

    #[ORM\Column(length: 24, enumType: QuoteRequestStatus::class)]
    private QuoteRequestStatus $status;

    /** @var Collection<int, QuoteResponse> */
    #[ORM\OneToMany(mappedBy: 'request', targetEntity: QuoteResponse::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $responses;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $customer,
        ?Vehicle $vehicle,
        ?int $mileage,
        string $symptomDescription,
        ?string $occurrenceConditions,
        bool $vehicleDrivable,
        ?string $warningLights,
        ?string $preferredContactMethod,
        ?string $preferredInterval,
        bool $asDraft = false,
    ) {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->vehicle = $vehicle;
        $this->mileage = $mileage;
        $this->symptomDescription = $symptomDescription;
        $this->occurrenceConditions = $occurrenceConditions;
        $this->vehicleDrivable = $vehicleDrivable;
        $this->warningLights = $warningLights;
        $this->preferredContactMethod = $preferredContactMethod;
        $this->preferredInterval = $preferredInterval;
        $this->status = $asDraft ? QuoteRequestStatus::DRAFT : QuoteRequestStatus::SUBMITTED;
        $this->responses = new ArrayCollection();
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

    public function mileage(): ?int
    {
        return $this->mileage;
    }

    public function symptomDescription(): string
    {
        return $this->symptomDescription;
    }

    public function occurrenceConditions(): ?string
    {
        return $this->occurrenceConditions;
    }

    public function vehicleDrivable(): bool
    {
        return $this->vehicleDrivable;
    }

    public function warningLights(): ?string
    {
        return $this->warningLights;
    }

    public function preferredContactMethod(): ?string
    {
        return $this->preferredContactMethod;
    }

    public function preferredInterval(): ?string
    {
        return $this->preferredInterval;
    }

    public function status(): QuoteRequestStatus
    {
        return $this->status;
    }

    /** @return QuoteResponse[] */
    public function responses(): array
    {
        return $this->responses->toArray();
    }

    public function changeStatus(QuoteRequestStatus $status): void
    {
        if (!$this->status->canTransitionTo($status)) {
            throw InvalidTransitionException::between($this->status, $status);
        }
        $this->status = $status;
        $this->touch();
    }

    /** Clientul își actualizează ciorna sau completează informațiile cerute. */
    public function updateDetails(
        ?int $mileage,
        string $symptomDescription,
        ?string $occurrenceConditions,
        bool $vehicleDrivable,
        ?string $warningLights,
        ?string $preferredContactMethod,
        ?string $preferredInterval,
    ): void {
        if (!\in_array($this->status, [QuoteRequestStatus::DRAFT, QuoteRequestStatus::NEEDS_INFORMATION], true)) {
            throw InvalidTransitionException::between($this->status, $this->status);
        }
        $this->mileage = $mileage;
        $this->symptomDescription = $symptomDescription;
        $this->occurrenceConditions = $occurrenceConditions;
        $this->vehicleDrivable = $vehicleDrivable;
        $this->warningLights = $warningLights;
        $this->preferredContactMethod = $preferredContactMethod;
        $this->preferredInterval = $preferredInterval;
        $this->touch();
    }

    public function addResponse(QuoteResponse $response): void
    {
        $this->responses->add($response);
        $this->touch();
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
