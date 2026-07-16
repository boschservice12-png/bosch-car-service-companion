<?php

declare(strict_types=1);

namespace App\DamageClaim\Infrastructure;

use App\DamageClaim\Domain\DamageClaim;
use App\DamageClaim\Domain\DamageClaimRepository;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineDamageClaimRepository implements DamageClaimRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(DamageClaim $claim): void
    {
        $this->em->persist($claim);
        $this->em->flush();
    }

    public function get(Uuid $id): ?DamageClaim
    {
        return $this->em->find(DamageClaim::class, $id);
    }

    /** @return DamageClaim[] */
    public function findForCustomer(User $customer): array
    {
        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(DamageClaim::class, 'd')
            ->where('d.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return DamageClaim[] */
    public function findAllForAdmin(): array
    {
        return $this->em->createQueryBuilder()
            ->select('d')
            ->from(DamageClaim::class, 'd')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
