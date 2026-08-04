<?php

declare(strict_types=1);

namespace App\Vehicle\Infrastructure;

use App\Vehicle\Domain\Vehicle;
use App\Vehicle\Domain\VehicleActivationToken;
use App\Vehicle\Domain\VehicleActivationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineVehicleActivationTokenRepository implements VehicleActivationTokenRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(VehicleActivationToken $token): void
    {
        $this->em->persist($token);
        $this->em->flush();
    }

    public function findByHash(string $tokenHash): ?VehicleActivationToken
    {
        return $this->em->getRepository(VehicleActivationToken::class)->findOneBy(['tokenHash' => $tokenHash]);
    }

    /** @return VehicleActivationToken[] */
    public function findLiveForVehicle(Vehicle $vehicle, \DateTimeImmutable $now): array
    {
        return $this->em->createQueryBuilder()
            ->select('t')
            ->from(VehicleActivationToken::class, 't')
            ->where('t.vehicle = :vehicle')
            ->andWhere('t.usedAt IS NULL')
            ->andWhere('t.revokedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('vehicle', $vehicle)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
