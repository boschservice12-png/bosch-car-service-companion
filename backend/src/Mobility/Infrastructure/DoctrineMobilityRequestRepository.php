<?php

declare(strict_types=1);

namespace App\Mobility\Infrastructure;

use App\Identity\Domain\User;
use App\Mobility\Domain\MobilityRequest;
use App\Mobility\Domain\MobilityRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineMobilityRequestRepository implements MobilityRequestRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(MobilityRequest $request): void
    {
        $this->em->persist($request);
        $this->em->flush();
    }

    public function get(Uuid $id): ?MobilityRequest
    {
        return $this->em->find(MobilityRequest::class, $id);
    }

    /** @return MobilityRequest[] */
    public function findForCustomer(User $customer): array
    {
        return $this->em->createQueryBuilder()
            ->select('m')
            ->from(MobilityRequest::class, 'm')
            ->where('m.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return MobilityRequest[] */
    public function findAllForAdmin(): array
    {
        return $this->em->createQueryBuilder()
            ->select('m')
            ->from(MobilityRequest::class, 'm')
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
