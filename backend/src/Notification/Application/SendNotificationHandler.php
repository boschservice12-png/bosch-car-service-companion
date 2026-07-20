<?php

declare(strict_types=1);

namespace App\Notification\Application;

use App\Audit\Application\AuditRecorder;
use App\Identity\Domain\User;
use App\Notification\Application\Message\SendNotification;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationDelivery;
use App\Notification\Domain\NotificationRepository;
use App\Notification\Domain\NotificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Consumă mesajele de notificare cu STĂRI ADEVĂRATE. O notificare NU e „trimisă"
 * doar pentru că există un rând: SENT înseamnă succesul unui furnizor automat.
 *
 *  PENDING → PROCESSING → (SENT | FAILED | MANUAL_ACTION_REQUIRED | SKIPPED)
 *
 * În pilot furnizorul implicit (ManualNotificationDelivery) nu trimite automat,
 * deci rezultatul e MANUAL_ACTION_REQUIRED (operatorul trimite manual) sau
 * SKIPPED (destinatar intern) — niciodată SENT „orb". Reîncercarea (Messenger)
 * rulează DOAR pentru furnizori automați care semnalează un eșec retryable.
 * Idempotent: la reîncercare/duplicat se refolosește notificarea (dedupKey).
 */
#[AsMessageHandler]
final class SendNotificationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
        private readonly NotificationDelivery $delivery,
        private readonly AuditRecorder $audit,
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

        // Idempotență: la reîncercare/duplicat refolosim notificarea existentă
        // în loc să creăm una nouă. Dacă e deja într-o stare terminală, ieșim.
        $notification = $message->dedupKey !== null
            ? $this->notifications->findByDedupKey($message->dedupKey)
            : null;
        if ($notification !== null && \in_array($notification->status(), [
            NotificationStatus::SENT,
            NotificationStatus::MANUAL_ACTION_REQUIRED,
            NotificationStatus::SKIPPED,
        ], true)) {
            return; // deja finalizată — nu dublăm
        }
        if ($notification === null) {
            $notification = new Notification($user, $message->type, $message->payload, $message->channel, $message->dedupKey);
            $this->em->persist($notification);
        }

        $notification->markProcessing();
        $this->em->flush();

        try {
            $result = $this->delivery->deliver($notification);
        } catch (\Throwable $e) {
            // Un furnizor automat a aruncat: marcăm FAILED, persistăm, apoi
            // re-aruncăm ca Messenger să reîncerce (retry_strategy → failed).
            $notification->markFailed($e->getMessage(), $notification->provider());
            $this->em->flush();
            $this->audit->record('notification.failed', 'Notification', (string) $notification->id(), null, [
                'reason' => $e->getMessage(),
            ]);

            throw $e;
        }

        match ($result->status) {
            NotificationStatus::SENT => $notification->markSent($result->provider ?? 'auto'),
            NotificationStatus::MANUAL_ACTION_REQUIRED => $notification->markManualActionRequired($result->reason),
            NotificationStatus::SKIPPED => $notification->markSkipped($result->reason),
            default => $notification->markFailed($result->reason ?? 'necunoscut', $result->provider),
        };
        $this->em->flush();

        $this->audit->record('notification.'.strtolower($notification->status()->value), 'Notification', (string) $notification->id(), null, [
            'type' => $message->type,
            'channel' => $notification->channel(),
            'provider' => $notification->provider(),
        ]);

        // Un eșec retryable de la un furnizor automat → lăsăm Messenger să reîncerce.
        if ($result->status === NotificationStatus::FAILED && $result->retryable) {
            throw new \RuntimeException('Livrare eșuată (retryable): '.($result->reason ?? ''));
        }
    }
}
