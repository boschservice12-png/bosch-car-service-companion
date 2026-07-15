<?php

declare(strict_types=1);

namespace App\Vehicle\Domain;

use App\Customer\Domain\CustomerProfile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Legătura proprietar↔vehicul. Un vehicul are cel mult un proprietar `active`
 * la un moment dat (istoricul de proprietari este suportat prin `active=false`).
 */
#[ORM\Entity]
#[ORM\Table(name: 'vehicle_ownerships')]
#[ORM\Index(name: 'ix_ownership_customer', columns: ['customer_profile_id'])]
class VehicleOwnership
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Vehicle $vehicle;

    #[ORM\ManyToOne(targetEntity: CustomerProfile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private CustomerProfile $customerProfile;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $validTo = null;

    public function __construct(Vehicle $vehicle, CustomerProfile $customerProfile)
    {
        $this->id = Uuid::v7();
        $this->vehicle = $vehicle;
        $this->customerProfile = $customerProfile;
        $this->validFrom = new \DateTimeImmutable('today');
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function vehicle(): Vehicle
    {
        return $this->vehicle;
    }

    public function customerProfile(): CustomerProfile
    {
        return $this->customerProfile;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function release(): void
    {
        $this->active = false;
        $this->validTo = new \DateTimeImmutable('today');
    }
}
