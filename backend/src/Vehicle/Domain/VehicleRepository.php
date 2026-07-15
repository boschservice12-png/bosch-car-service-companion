<?php

declare(strict_types=1);

namespace App\Vehicle\Domain;

use App\Customer\Domain\CustomerProfile;
use Symfony\Component\Uid\Uuid;

interface VehicleRepository
{
    public function save(Vehicle $vehicle): void;

    public function get(Uuid $id): ?Vehicle;

    /** @return Vehicle[] */
    public function findActiveForCustomer(CustomerProfile $customer): array;

    /** Verifică dacă un client este proprietarul activ al vehiculului (autorizare la nivel de obiect). */
    public function isActiveOwner(Vehicle $vehicle, CustomerProfile $customer): bool;

    public function assignOwner(Vehicle $vehicle, CustomerProfile $customer): void;
}
