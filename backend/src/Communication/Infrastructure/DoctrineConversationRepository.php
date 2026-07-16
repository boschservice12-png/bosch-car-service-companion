<?php

declare(strict_types=1);

namespace App\Communication\Infrastructure;

use App\Communication\Domain\Conversation;
use App\Communication\Domain\ConversationRepository;
use App\Identity\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineConversationRepository implements ConversationRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Conversation $conversation): void
    {
        // Mesajele noi sunt persistate în cascadă prin colecția conversației.
        $this->em->persist($conversation);
        $this->em->flush();
    }

    public function get(Uuid $id): ?Conversation
    {
        return $this->em->find(Conversation::class, $id);
    }

    /** @return Conversation[] */
    public function findForCustomer(User $customer): array
    {
        return $this->em->createQueryBuilder()
            ->select('c')
            ->from(Conversation::class, 'c')
            ->where('c.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Conversation[] */
    public function findAllForAdmin(): array
    {
        return $this->em->createQueryBuilder()
            ->select('c')
            ->from(Conversation::class, 'c')
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
