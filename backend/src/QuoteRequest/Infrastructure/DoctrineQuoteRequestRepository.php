<?php

declare(strict_types=1);

namespace App\QuoteRequest\Infrastructure;

use App\Identity\Domain\User;
use App\QuoteRequest\Domain\QuoteRequest;
use App\QuoteRequest\Domain\QuoteRequestRepository;
use App\QuoteRequest\Domain\QuoteRequestStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineQuoteRequestRepository implements QuoteRequestRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(QuoteRequest $request): void
    {
        $this->em->persist($request);
        $this->em->flush();
    }

    public function get(Uuid $id): ?QuoteRequest
    {
        return $this->em->find(QuoteRequest::class, $id);
    }

    /** @return QuoteRequest[] */
    public function findForCustomer(User $customer): array
    {
        return $this->em->createQueryBuilder()
            ->select('q')
            ->from(QuoteRequest::class, 'q')
            ->where('q.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return QuoteRequest[] Ciornele clientului nu apar în portalul service. */
    public function findAllForAdmin(): array
    {
        return $this->em->createQueryBuilder()
            ->select('q')
            ->from(QuoteRequest::class, 'q')
            ->where('q.status != :draft')
            ->setParameter('draft', QuoteRequestStatus::DRAFT)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
