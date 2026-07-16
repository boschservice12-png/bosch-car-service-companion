<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Identity\Domain\User;
use App\Notification\Application\Message\SendNotification;
use App\Notification\Domain\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Consumă mesajele de notificare: persistă notificarea și loghează structurat
 * (fără conținut sensibil). Livrarea reală pe canal (email/push) se activează
 * pe măsură ce sunt confirmați furnizorii (vezi ADR-0002 / întrebări blocante).
 */
#[AsMessageHandler]
final class SendNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendNotification $message): void
    {
        $user = $this->em->find(User::class, Uuid::fromString($message->userId));
        if ($user === null) {
            $this->logger->warning('notification.user_missing', ['userId' => $message->userId]);

            return;
        }

        $notification = new Notification($user, $message->type, $message->payload, $message->channel);
        $notification->markSent();
        $this->em->persist($notification);
        $this->em->flush();

        $this->logger->info('notification.dispatched', [
            'userId' => $message->userId,
            'type' => $message->type,
            'channel' => $message->channel,
        ]);
    }
}
