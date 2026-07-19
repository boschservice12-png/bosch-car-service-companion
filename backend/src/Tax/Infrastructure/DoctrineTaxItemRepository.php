<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure;

use App\Identity\Domain\User;
use App\Tax\Domain\TaxItem;
use App\Tax\Domain\TaxItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineTaxItemRepository implements TaxItemRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(TaxItem $item): void
    {
        $this->em->persist($item);
        $this->em->flush();
    }

    public function remove(TaxItem $item): void
    {
        $this->em->remove($item);
        $this->em->flush();
    }

    public function get(Uuid $id): ?TaxItem
    {
        return $this->em->find(TaxItem::class, $id);
    }

    /** @return TaxItem[] */
    public function findForCustomer(User $customer): array
    {
        return $this->em->createQueryBuilder()
            ->select('t')
            ->from(TaxItem::class, 't')
            ->where('t.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('t.year', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return TaxItem[] */
    public function findAllForAdmin(): array
    {
        return $this->em->createQueryBuilder()
            ->select('t')
            ->from(TaxItem::class, 't')
            ->orderBy('t.year', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
