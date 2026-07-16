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

    /** Toate vehiculele service-ului (pentru portalul admin). @return Vehicle[] */
    public function findAllActive(): array;

    /** Verifică dacă un client este proprietarul activ al vehiculului (autorizare la nivel de obiect). */
    public function isActiveOwner(Vehicle $vehicle, CustomerProfile $customer): bool;

    /** Proprietarul activ al vehiculului (dacă există). */
    public function findActiveOwner(Vehicle $vehicle): ?CustomerProfile;

    public function assignOwner(Vehicle $vehicle, CustomerProfile $customer): void;
}
