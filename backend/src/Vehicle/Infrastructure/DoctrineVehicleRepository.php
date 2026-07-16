<?php

declare(strict_types=1);

namespace App\Vehicle\Infrastructure;

use App\Customer\Domain\CustomerProfile;
use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleOwnership;
use App\Vehicle\Domain\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineVehicleRepository implements VehicleRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Vehicle $vehicle): void
    {
        $this->em->persist($vehicle);
        $this->em->flush();
    }

    public function get(Uuid $id): ?Vehicle
    {
        $vehicle = $this->em->find(Vehicle::class, $id);

        return $vehicle instanceof Vehicle && !$vehicle->isDeleted() ? $vehicle : null;
    }

    /** @return Vehicle[] */
    public function findActiveForCustomer(CustomerProfile $customer): array
    {
        return $this->em->createQueryBuilder()
            ->select('v')
            ->from(Vehicle::class, 'v')
            ->join(VehicleOwnership::class, 'o', 'WITH', 'o.vehicle = v')
            ->where('o.customerProfile = :customer')
            ->andWhere('o.active = :active')
            ->andWhere('v.deletedAt IS NULL')
            ->setParameter('customer', $customer)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function isActiveOwner(Vehicle $vehicle, CustomerProfile $customer): bool
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(VehicleOwnership::class, 'o')
            ->where('o.vehicle = :vehicle')
            ->andWhere('o.customerProfile = :customer')
            ->andWhere('o.active = :active')
            ->setParameter('vehicle', $vehicle)
            ->setParameter('customer', $customer)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findActiveOwner(Vehicle $vehicle): ?CustomerProfile
    {
        $ownership = $this->em->createQueryBuilder()
            ->select('o')
            ->from(VehicleOwnership::class, 'o')
            ->where('o.vehicle = :vehicle')
            ->andWhere('o.active = :active')
            ->setParameter('vehicle', $vehicle)
            ->setParameter('active', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $ownership?->customerProfile();
    }

    public function assignOwner(Vehicle $vehicle, CustomerProfile $customer): void
    {
        $ownership = new VehicleOwnership($vehicle, $customer);
        $this->em->persist($ownership);
        $this->em->flush();
    }
}
