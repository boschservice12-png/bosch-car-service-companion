<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineNotificationRepository implements NotificationRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(Notification $notification): void
    {
        $this->em->persist($notification);
        $this->em->flush();
    }

    public function get(Uuid $id): ?Notification
    {
        return $this->em->find(Notification::class, $id);
    }

    public function findByDedupKey(string $dedupKey): ?Notification
    {
        return $this->em->getRepository(Notification::class)->findOneBy(['dedupKey' => $dedupKey]);
    }

    /** @return Notification[] */
    public function findRecent(int $limit = 100): array
    {
        return $this->em->getRepository(Notification::class)
            ->findBy([], ['createdAt' => 'DESC'], $limit);
    }
}
