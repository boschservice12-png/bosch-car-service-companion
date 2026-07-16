<?php

declare(strict_types=1);

namespace App\Roadside\Infrastructure;

use App\Identity\Domain\User;
use App\Roadside\Domain\RoadsideRequest;
use App\Roadside\Domain\RoadsideRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineRoadsideRequestRepository implements RoadsideRequestRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(RoadsideRequest $request): void
    {
        $this->em->persist($request);
        $this->em->flush();
    }

    public function get(Uuid $id): ?RoadsideRequest
    {
        return $this->em->find(RoadsideRequest::class, $id);
    }

    /** @return RoadsideRequest[] */
    public function findForCustomer(User $customer): array
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(RoadsideRequest::class, 'r')
            ->where('r.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return RoadsideRequest[] */
    public function findAllForAdmin(): array
    {
        return $this->em->createQueryBuilder()
            ->select('r')
            ->from(RoadsideRequest::class, 'r')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
