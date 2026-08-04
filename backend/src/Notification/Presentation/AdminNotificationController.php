<?php

declare(strict_types=1);

namespace App\Notification\Presentation;

use App\Audit\Application\AuditRecorder;
use App\Identity\Domain\User;
use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationRepository;
use App\Notification\Domain\NotificationStatus;
use App\Shared\Presentation\ValidationFailedException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Portal service (ADMIN) — vizibilitate asupra stărilor REALE de notificare și
 * confirmarea manuală a trimiterii. `/api/admin` e păzit de ROLE_SERVICE_ADMIN.
 *
 * O notificare devine SENT prin acest endpoint DOAR ca urmare a unei acțiuni
 * explicite de admin (a trimis efectiv mesajul, ex. WhatsApp de pe numărul
 * service-ului). Fără această confirmare rămâne MANUAL_ACTION_REQUIRED.
 */
final class AdminNotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
        private readonly AuditRecorder $audit,
    ) {
    }

    /** Notificările recente cu starea lor reală (pentru a distinge în UI). */
    #[Route('/api/admin/notifications', name: 'admin_notifications_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map($this->serialize(...), $this->notifications->findRecent(100)));
    }

    /** Confirmă că notificarea a fost trimisă MANUAL de operator → SENT (auditat). */
    #[Route('/api/admin/notifications/{id}/manually-sent', name: 'admin_notifications_manual_sent', methods: ['POST'])]
    public function markManuallySent(string $id, Request $request): JsonResponse
    {
        if (!Uuid::isValid($id) || ($notification = $this->notifications->get(Uuid::fromString($id))) === null) {
            throw $this->createNotFoundException('Notificare inexistentă.');
        }

        /** @var array{channel?: string, note?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $channel = \is_string($payload['channel'] ?? null) ? trim($payload['channel']) : '';
        if ($channel === '') {
            throw ValidationFailedException::fromArray(['channel' => ['Canalul trimiterii manuale este obligatoriu.']]);
        }
        $note = \is_string($payload['note'] ?? null) ? $payload['note'] : null;

        /** @var User $admin */
        $admin = $this->getUser();
        $notification->markManuallySent($admin, $channel, $note);
        $this->em->flush();

        $this->audit->record('notification.manually_sent', 'Notification', $id, null, [
            'channel' => $channel,
            'sentBy' => (string) $admin->id(),
        ]);

        return $this->json($this->serialize($notification));
    }

    /** @return array<string, mixed> */
    private function serialize(Notification $n): array
    {
        return [
            'id' => (string) $n->id(),
            'channel' => $n->channel(),
            'status' => $n->status()->value,
            'attempts' => $n->attempts(),
            'provider' => $n->provider(),
            'failureReason' => $n->failureReason(),
            'sentAt' => $n->sentAt()?->format(\DateTimeInterface::ATOM),
            'createdAt' => $n->createdAt()->format(\DateTimeInterface::ATOM),
            'manualActionRequired' => $n->status() === NotificationStatus::MANUAL_ACTION_REQUIRED,
        ];
    }
}
