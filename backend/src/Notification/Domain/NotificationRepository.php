<?php

declare(strict_types=1);

namespace App\Notification\Domain;

use Symfony\Component\Uid\Uuid;

interface NotificationRepository
{
    public function save(Notification $notification): void;

    public function get(Uuid $id): ?Notification;

    public function findByDedupKey(string $dedupKey): ?Notification;

    /** @return Notification[] Cele mai recente notificări (pentru portalul admin). */
    public function findRecent(int $limit = 100): array;
}
