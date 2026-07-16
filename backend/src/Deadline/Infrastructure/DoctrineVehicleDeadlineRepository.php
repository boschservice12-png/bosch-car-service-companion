<?php

declare(strict_types=1);

namespace App\Deadline\Infrastructure;

use App\Deadline\Domain\DeadlineNotification;
use App\Deadline\Domain\VehicleDeadline;
use App\Deadline\Domain\VehicleDeadlineRepository;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineVehicleDeadlineRepository implements VehicleDeadlineRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(VehicleDeadline $deadline): void
    {
        $this->em->persist($deadline);
        $this->em->flush();
    }

    public function get(Uuid $id): ?VehicleDeadline
    {
        return $this->em->find(VehicleDeadline::class, $id);
    }

    /** @return VehicleDeadline[] */
    public function findForVehicle(Vehicle $vehicle): array
    {
        return $this->em->getRepository(VehicleDeadline::class)
            ->findBy(['vehicle' => $vehicle], ['type' => 'ASC']);
    }

    /** @return VehicleDeadline[] */
    public function findWithExpiry(): array
    {
        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(VehicleDeadline::class, 'd')
            ->where('d.expiresAt IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    public function notificationAlreadySent(VehicleDeadline $deadline, int $thresholdDays, string $channel): bool
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(DeadlineNotification::class, 'n')
            ->where('n.deadline = :deadline')
            ->andWhere('n.thresholdDays = :threshold')
            ->andWhere('n.channel = :channel')
            ->setParameter('deadline', $deadline)
            ->setParameter('threshold', $thresholdDays)
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function recordNotification(DeadlineNotification $notification): void
    {
        $this->em->persist($notification);
        $this->em->flush();
    }
}
