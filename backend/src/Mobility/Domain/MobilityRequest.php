<?php

declare(strict_types=1);

namespace App\Mobility\Domain;

use App\Identity\Domain\User;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * O solicitare de mobilitate (mașină de înlocuire, taxi, transport acasă etc.)
 * deschisă de un client pe durata unei reparații. Aparține clientului (autorizare
 * la nivel de obiect); service-ul gestionează starea.
 */
#[ORM\Entity]
#[ORM\Table(name: 'mobility_requests')]
#[ORM\Index(name: 'ix_mobility_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'ix_mobility_status', columns: ['status'])]
class MobilityRequest
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

    #[ORM\Column(length: 20, enumType: MobilityType::class)]
    private MobilityType $type;

    #[ORM\Column(type: 'text')]
    private string $details;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $preferredDate;

    #[ORM\Column(length: 16, enumType: MobilityStatus::class)]
    private MobilityStatus $status = MobilityStatus::NEW;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $customer,
        ?Vehicle $vehicle,
        MobilityType $type,
        string $details,
        ?\DateTimeImmutable $preferredDate,
    ) {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->vehicle = $vehicle;
        $this->type = $type;
        $this->details = $details;
        $this->preferredDate = $preferredDate;
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

    public function type(): MobilityType
    {
        return $this->type;
    }

    public function details(): string
    {
        return $this->details;
    }

    public function preferredDate(): ?\DateTimeImmutable
    {
        return $this->preferredDate;
    }

    public function status(): MobilityStatus
    {
        return $this->status;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function isOpen(): bool
    {
        return $this->status === MobilityStatus::NEW;
    }

    public function changeStatus(MobilityStatus $status, ?string $note): void
    {
        $this->status = $status;
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
