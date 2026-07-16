<?php

declare(strict_types=1);

namespace App\ServiceHistory\Infrastructure;

use App\ServiceHistory\Domain\ServiceRecord;
use App\ServiceHistory\Domain\ServiceRecordRepository;
use App\ServiceHistory\Domain\ServiceRecordStatus;
use App\Vehicle\Domain\Vehicle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineServiceRecordRepository implements ServiceRecordRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(ServiceRecord $record): void
    {
        $this->em->persist($record);
        $this->em->flush();
    }

    public function get(Uuid $id): ?ServiceRecord
    {
        return $this->em->find(ServiceRecord::class, $id);
    }

    /** @return ServiceRecord[] */
    public function findForVehicle(Vehicle $vehicle, bool $includeDrafts): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(ServiceRecord::class, 'r')
            ->where('r.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            // Cronologic: data serviciului, apoi momentul creării.
            ->orderBy('r.serviceDate', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC');

        if (!$includeDrafts) {
            $qb->andWhere('r.status = :published')
                ->setParameter('published', ServiceRecordStatus::PUBLISHED);
        }

        return $qb->getQuery()->getResult();
    }
}
